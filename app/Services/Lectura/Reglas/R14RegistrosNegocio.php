<?php

namespace App\Services\Lectura\Reglas;

use App\Enums\Lectura\SentidoRenglon;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Lectura\DatosDocumento;
use Illuminate\Support\Collection;

/**
 * R-14 (revisión), primera versión (F6 6.3b): "Registros del negocio por
 * revisar". Una excepción por archivo y por tipo de problema, nunca por
 * renglón:
 *
 * - `hoja`: la hoja trajo algo que no se reconoció (columnas sin identificar,
 *   filas sin monto o sin fecha, fórmulas sin valor, otras hojas sin leer, o
 *   no se reconoció como plantilla ni como hoja de movimientos).
 * - `sin_categoria`: renglones que quedaron `no_se` sin categoría.
 * - `archivo_sin_lector`: un PDF o una foto de registros; el lector con IA
 *   llega en una versión posterior y por ahora lo lee el preparador.
 *
 * El detalle lleva ids, conteos y letras de columna; nunca montos ni
 * descripciones. Lo que el preparador corrige (le pone categoría a un
 * renglón) deja de aparecer y la excepción pasa a corregida.
 */
class R14RegistrosNegocio implements Regla
{
    /** @var list<string> */
    private const FORMATOS_HOJA = ['xlsx', 'csv'];

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly DatosDocumento $datos,
    ) {}

    public function codigo(): string
    {
        return 'R-14';
    }

    public function evaluar(Entrevista $entrevista, Collection $datosPorItem): array
    {
        $codigos = $this->catalogo->documentos($entrevista->tax_year)->where('lectura', 'renglones')->pluck('codigo')->all();
        $items = $entrevista->documentos()->vigentes()->whereIn('documento_codigo', $codigos)->get();

        if ($items->isEmpty()) {
            return [];
        }

        $lecturas = $this->datos->lecturasVigentes($items->modelKeys())->keyBy('documento_id');
        $sinCategoria = $this->datos->renglonesVigentes($items->modelKeys())
            ->filter(fn ($r) => $r->categoria === null && $r->sentido === SentidoRenglon::NoSe)
            ->countBy('lectura_id');
        $hallazgos = [];

        foreach ($items as $item) {
            foreach (Documento::query()->where('entrevista_documento_id', $item->id)->get(['id', 'formato']) as $documento) {
                $base = "R-14#item:{$item->id}#documento:{$documento->id}";

                if (! in_array(strtolower((string) $documento->formato), self::FORMATOS_HOJA, true)) {
                    $hallazgos[] = new Hallazgo("{$base}#archivo_sin_lector", $item, detalle: ['tipo' => 'archivo_sin_lector', 'documento_id' => $documento->id]);

                    continue;
                }

                $lectura = $lecturas->get($documento->id);

                if ($lectura === null) {
                    continue;
                }

                $observaciones = is_array($lectura->observaciones) ? $lectura->observaciones : [];

                if ($observaciones !== []) {
                    $hallazgos[] = new Hallazgo("{$base}#hoja", $item, detalle: ['tipo' => 'hoja', 'documento_id' => $documento->id, 'lectura_id' => $lectura->id, ...$observaciones]);
                }

                if (($sinCategoria[$lectura->id] ?? 0) > 0) {
                    $hallazgos[] = new Hallazgo("{$base}#sin_categoria", $item, detalle: ['tipo' => 'sin_categoria', 'documento_id' => $documento->id, 'lectura_id' => $lectura->id, 'renglones' => (int) $sinCategoria[$lectura->id]]);
                }
            }
        }

        return $hallazgos;
    }
}
