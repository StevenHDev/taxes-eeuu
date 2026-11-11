<?php

namespace App\Services\Lectura\Reglas;

use App\Enums\Lectura\SentidoRenglon;
use App\Models\Documento;
use App\Models\Entrevista\Entrevista;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Lectura\DatosDocumento;
use App\Services\Lectura\LectorOpenAi;
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
 * - `archivo_sin_lector`: un PDF o una foto de registros que no se leyó solo
 *   (la lectura automática apagada, o una lectura que no terminó): lo lee el
 *   preparador. Desde 6.3c, un PDF o una foto leídos con IA ya no caen aquí.
 * - `demasiadas_paginas` (6.3c): el archivo pasa del máximo de páginas por
 *   archivo (`lectura.maximo_paginas_renglones`) y no se leyó.
 * - `tope_alcanzado` (6.3c): el cliente llegó al tope de páginas leídas con IA
 *   del año (`lectura.maximo_paginas_ia_por_cliente`) y el archivo no se mandó
 *   a OpenAI.
 *
 * El detalle lleva ids, conteos y letras de columna; nunca montos ni
 * descripciones. Lo que el preparador corrige (le pone categoría a un
 * renglón) deja de aparecer y la excepción pasa a corregida.
 */
class R14RegistrosNegocio implements Regla
{
    /** @var list<string> */
    private const FORMATOS_HOJA = ['xlsx', 'csv'];

    /** Los archivos que no se mandaron a la IA (`observaciones.sin_leer` de la lectura, 6.3c). */
    public const SIN_LEER = ['demasiadas_paginas', 'tope_alcanzado'];

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
            // Sin categoría no suma: un ingreso, un gasto o un renglón que no se sabe qué es (no las transferencias ni lo personal).
            ->filter(fn ($r) => $r->categoria === null && in_array($r->sentido, [SentidoRenglon::Ingreso, SentidoRenglon::Gasto, SentidoRenglon::NoSe], true))
            ->countBy('lectura_id');
        $hallazgos = [];

        foreach ($items as $item) {
            foreach (Documento::query()->where('entrevista_documento_id', $item->id)->get(['id', 'formato']) as $documento) {
                $base = "R-14#item:{$item->id}#documento:{$documento->id}";

                $lectura = $lecturas->get($documento->id);
                $esHoja = in_array(strtolower((string) $documento->formato), self::FORMATOS_HOJA, true);

                // Un PDF o una foto sin lectura con IA terminada: lo revisa el preparador.
                if (! $esHoja && ($lectura === null || $lectura->lector !== LectorOpenAi::NOMBRE)) {
                    $hallazgos[] = new Hallazgo("{$base}#archivo_sin_lector", $item, detalle: ['tipo' => 'archivo_sin_lector', 'documento_id' => $documento->id]);

                    continue;
                }

                if ($lectura === null) {
                    continue;
                }

                $observaciones = is_array($lectura->observaciones) ? $lectura->observaciones : [];
                $sinLeer = $observaciones['sin_leer'] ?? null;

                if (in_array($sinLeer, self::SIN_LEER, true)) {
                    $hallazgos[] = new Hallazgo("{$base}#{$sinLeer}", $item, detalle: ['tipo' => $sinLeer, 'documento_id' => $documento->id, 'lectura_id' => $lectura->id, ...array_diff_key($observaciones, ['sin_leer' => true])]);

                    continue;
                }

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

    /**
     * Lo que se le dice al cliente de un archivo de registros que no se leyó
     * (6.3c), para que sepa qué subir. Nunca lleva montos; null si el tipo no
     * le pide nada al cliente. Lo usa la explicación al preparador (para que
     * se lo mande con «Observar») y lo usará el portal (6.3e).
     */
    public static function textoParaCliente(string $tipo): ?string
    {
        return match ($tipo) {
            'demasiadas_paginas' => __('Este archivo tiene demasiadas páginas para leerlo. Súbelo por meses, un archivo por mes. Si llevas tus cuentas, la plantilla es lo más rápido.'),
            'tope_alcanzado' => __('Ya recibimos muchos extractos y recibos tuyos este año; tu preparador revisará este archivo. Si llevas tus cuentas, la plantilla es lo más rápido.'),
            default => null,
        };
    }
}
