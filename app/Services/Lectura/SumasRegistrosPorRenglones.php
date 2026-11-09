<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\MotivoConfianza;
use App\Enums\Lectura\OrigenDato;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Expediente\Expediente;
use App\Models\Lectura\DocumentoRenglon;
use App\Services\Calculo\Nucleo\Dinero;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Suma por categoría los renglones vigentes de los registros del negocio del
 * cliente (F6 6.3b, tramo 1): los de las hojas y plantillas `xlsx`/`csv` de
 * todos sus archivos de `registros-ingresos-gastos`. Deja fuera `transferencia`,
 * `personal`, `no_se`, los renglones sin categoría y los que ya reporta un 1099
 * (`cubierto_por_item_id`). Las cantidades (millas y pies cuadrados) salen de
 * las casillas fijas del documento. El cuadre y la conciliación son del tramo 2.
 *
 * La confianza de una suma es la menor de sus renglones; un renglón que el
 * preparador corrigió cuenta como revisado por una persona (1,00), igual que
 * R-03 no cuestiona lo corregido.
 */
class SumasRegistrosPorRenglones implements SumasRegistros
{
    public const DOCUMENTO = 'registros-ingresos-gastos';

    /** @var array<string, string> casilla del documento → clave de las cantidades */
    private const CANTIDADES = [
        'millas-negocio' => 'millas',
        'pies-cuadrados-casa' => 'pies-casa',
        'pies-cuadrados-oficina' => 'pies-oficina',
    ];

    public function __construct(private readonly DatosDocumento $datos) {}

    public function para(Expediente $expediente): ResultadoSumas
    {
        // Sin entrevista no hay ítems (el id 0 no existe).
        $items = EntrevistaDocumento::query()
            ->where('entrevista_id', $expediente->entrevista_id ?? 0)
            ->where('documento_codigo', self::DOCUMENTO)
            ->vigentes()
            ->get();

        // Las líneas '2', '8'… son claves numéricas en PHP: `array_merge` las renumeraría, `array_replace` no.
        return new ResultadoSumas(array_replace(
            array_combine(ResultadoSumas::CLAVES, array_map(fn (string $linea) => $this->vacia($linea), ResultadoSumas::CLAVES)),
            $this->sumasDeRenglones($this->datos->renglonesVigentes($items->modelKeys())->groupBy('categoria')),
            $this->cantidades($items),
        ));
    }

    /**
     * @param  Collection<int|string, Collection<int, DocumentoRenglon>>  $porCategoria
     * @return array<string, SumaCategoria>
     */
    private function sumasDeRenglones($porCategoria): array
    {
        $sumas = [];

        foreach (ResultadoSumas::LINEAS as $linea) {
            $renglones = ($porCategoria->get($linea) ?? collect())
                ->filter(fn (DocumentoRenglon $r) => $r->sentido->suma() && $r->cubierto_por_item_id === null && $r->monto !== null)
                ->values();

            if ($renglones->isEmpty()) {
                continue;
            }

            $total = Dinero::deCentavos(0);
            $menos = null;

            foreach ($renglones as $renglon) {
                $total = $total->mas(Dinero::deDolares($renglon->monto));
                $peor = $this->confianzaDe($renglon);

                if ($menos === null || $peor['confianza'] < $menos['confianza']) {
                    $menos = $peor;
                }
            }

            $sumas[$linea] = new SumaCategoria(
                linea: $linea,
                total: $total,
                renglonIds: $renglones->pluck('id')->map(fn ($id) => (int) $id)->all(),
                confianza: $menos['confianza'],
                motivo: $renglones->count() === 1
                    ? __('Un solo renglón: :motivo', ['motivo' => $menos['texto']])
                    : __('Suma de :n renglones; el menos seguro: :motivo', ['n' => $renglones->count(), 'motivo' => $menos['texto']]),
                conDatos: true,
            );
        }

        return $sumas;
    }

    /**
     * @param  Collection<int, EntrevistaDocumento>  $items
     * @return array<string, SumaCategoria>
     */
    private function cantidades($items): array
    {
        $porClave = [];

        foreach ($items as $item) {
            foreach ($this->datos->vigentes($item) as $dato) {
                $clave = self::CANTIDADES[$dato->casilla] ?? null;

                if ($clave === null || $dato->valor === null || $dato->valor === '') {
                    continue;
                }

                try {
                    $valor = Dinero::deDolares((string) $dato->valor);
                } catch (InvalidArgumentException) {
                    continue;
                }

                $porClave[$clave][] = ['valor' => $valor, 'dato' => $dato];
            }
        }

        $sumas = [];

        foreach ($porClave as $clave => $lecturas) {
            // Las millas de varios registros se suman; los pies cuadrados no: son uno solo.
            $suma = $clave === 'millas';
            $total = $suma
                ? array_reduce($lecturas, fn (Dinero $t, array $l) => $t->mas($l['valor']), Dinero::deCentavos(0))
                : end($lecturas)['valor'];
            $distintos = ! $suma && count(array_unique(array_map(fn (array $l) => $l['valor']->centavos(), $lecturas))) > 1;

            $menos = null;

            foreach ($lecturas as $lectura) {
                $dato = $lectura['dato'];
                $peor = $dato->origen === OrigenDato::Preparador
                    ? ['confianza' => 1.0, 'texto' => __('Corregido por el preparador')]
                    : ['confianza' => (float) ($dato->confianza ?? 0), 'texto' => ($dato->motivo_confianza ?? MotivoConfianza::SinValor)->titulo()];
                $menos = $menos === null || $peor['confianza'] < $menos['confianza'] ? $peor : $menos;
            }

            $sumas[$clave] = new SumaCategoria(
                linea: $clave,
                total: $total,
                renglonIds: [],
                confianza: $distintos ? min($menos['confianza'], CalculadoraConfianza::REVISION) : $menos['confianza'],
                motivo: $distintos ? __('Varios archivos traen valores distintos: se tomó el más reciente') : $menos['texto'],
                conDatos: true,
            );
        }

        return $sumas;
    }

    /**
     * @return array{confianza: float, texto: string}
     */
    private function confianzaDe(DocumentoRenglon $renglon): array
    {
        if ($renglon->origen === OrigenDato::Preparador) {
            return ['confianza' => 1.0, 'texto' => __('Corregido por el preparador')];
        }

        return [
            'confianza' => (float) ($renglon->confianza ?? 0),
            'texto' => ($renglon->motivo_confianza ?? MotivoConfianza::SinValor)->titulo(),
        ];
    }

    private function vacia(string $linea): SumaCategoria
    {
        return new SumaCategoria($linea, Dinero::deCentavos(0), [], 0.0, __('Sin registros'), false);
    }
}
