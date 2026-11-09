<?php

namespace App\Services\Lectura;

use App\Enums\Lectura\EstadoLectura;
use App\Enums\Lectura\OrigenCategoria;
use App\Enums\Lectura\OrigenDato;
use App\Enums\Lectura\SentidoRenglon;
use App\Models\Entrevista\EntrevistaDocumento;
use App\Models\Lectura\DocumentoDato;
use App\Models\Lectura\DocumentoLectura;
use App\Models\Lectura\DocumentoRenglon;
use App\Models\User;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Entrevista\TransicionNoPermitida;
use App\Services\Lectura\Reglas\MotorReglas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Los datos leídos de los documentos (F4.2). Es el único que los corrige:
 * guarda el historial, nunca pisa el valor leído y, si el documento ya
 * estaba validado, reabre la validación (documento madre, 6.4.3).
 */
class DatosDocumento
{
    public function __construct(private readonly ChecklistDocumentos $checklist) {}

    /**
     * Los datos que cuentan de un ítem: los de la última lectura terminada
     * de cada uno de sus archivos vigentes.
     *
     * @return Collection<int, DocumentoDato>
     */
    public function vigentes(EntrevistaDocumento $item): Collection
    {
        return DocumentoDato::query()
            ->whereIn('lectura_id', $this->lecturasVigentes([$item->id])->modelKeys())
            ->orderBy('lectura_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * Los renglones que cuentan de uno o varios ítems (F6 6.3b): los de la última
     * lectura terminada de cada archivo vigente, en una sola consulta para todos.
     *
     * @param  list<int>  $itemIds
     * @return Collection<int, DocumentoRenglon>
     */
    public function renglonesVigentes(array $itemIds): Collection
    {
        return DocumentoRenglon::query()
            ->whereIn('lectura_id', $this->lecturasVigentes($itemIds)->modelKeys())
            ->orderBy('lectura_id')
            ->orderBy('numero')
            ->get();
    }

    /**
     * La última lectura terminada de cada archivo vigente de los ítems.
     *
     * @param  list<int>  $itemIds
     * @return Collection<int, DocumentoLectura>
     */
    public function lecturasVigentes(array $itemIds): Collection
    {
        return DocumentoLectura::query()
            ->whereIn('entrevista_documento_id', $itemIds)
            ->where('estado', EstadoLectura::Leida)
            ->whereHas('documento')
            ->orderByDesc('id')
            ->get()
            ->unique('documento_id');
    }

    /**
     * Guarda lo que leyó un lector (F4.3d): un dato por casilla, con el valor
     * leído (que no se pisa nunca) y su confianza con el motivo, y deja la
     * lectura `leida`. Releer es otra lectura; la anterior no se toca.
     *
     * @throws RuntimeException si la lectura ya terminó
     */
    public function guardarLectura(DocumentoLectura $lectura, LecturaResultado $resultado): void
    {
        throw_if($lectura->estado !== EstadoLectura::EnCola, new RuntimeException('La lectura ya terminó: para volver a leer se crea otra.'));

        DB::transaction(function () use ($lectura, $resultado) {
            foreach ($resultado->casillas as $casilla => $dato) {
                $lectura->datos()->create([
                    'entrevista_documento_id' => $lectura->entrevista_documento_id,
                    'casilla' => (string) $casilla,
                    'valor' => $dato['valor'],
                    'valor_leido' => $dato['valor'],
                    'confianza' => $dato['confianza'],
                    'motivo_confianza' => $dato['motivo'],
                    'legibilidad' => $dato['legibilidad'],
                    'pagina' => $dato['pagina'],
                    'recuadro' => $dato['recuadro'],
                    'evidencia' => $dato['evidencia'],
                    'origen' => OrigenDato::Lector,
                ]);
            }

            foreach ($resultado->renglones as $renglon) {
                $lectura->renglones()->create([
                    'entrevista_documento_id' => $lectura->entrevista_documento_id,
                    'numero' => $renglon->numero,
                    'pagina' => $renglon->pagina,
                    'recuadro' => $renglon->recuadro,
                    'fecha' => $renglon->fecha,
                    'descripcion' => $renglon->descripcion,
                    'monto' => $renglon->monto,
                    'sentido' => $renglon->sentido,
                    'categoria' => $renglon->categoria,
                    'categoria_origen' => $renglon->categoriaOrigen,
                    'confianza' => $renglon->confianza,
                    'motivo_confianza' => $renglon->motivo,
                    'legibilidad' => $renglon->legibilidad,
                    'evidencia' => $renglon->evidencia,
                    'origen' => OrigenDato::Lector,
                ]);
            }

            $lectura->update([
                'lector' => $resultado->lector,
                'modelo' => $resultado->modelo,
                'estado' => EstadoLectura::Leida,
                'tipo_detectado' => $resultado->tipoDetectado,
                'anio_detectado' => $resultado->anioDetectado,
                'clase_detectada' => $resultado->claseDetectada,
                'periodo_desde' => $resultado->periodoDesde,
                'periodo_hasta' => $resultado->periodoHasta,
                'observaciones' => $resultado->observaciones,
                'paginas' => $resultado->paginas > 0 ? $resultado->paginas : (int) $lectura->paginas,
                'error' => null,
                'terminada_en' => now(),
            ]);
        });
    }

    /**
     * Marca la lectura como fallida. El error guarda solo qué pasó, nunca lo
     * leído: los mensajes de nuestros lectores no llevan datos del cliente
     * (RuntimeException); de cualquier otra excepción solo se guarda su clase.
     */
    public function fallar(DocumentoLectura $lectura, Throwable $error): void
    {
        $lectura->update([
            'estado' => EstadoLectura::Fallida,
            'error' => $error instanceof RuntimeException ? mb_substr($error->getMessage(), 0, 500) : 'Error inesperado ('.class_basename($error).').',
            'terminada_en' => now(),
        ]);
    }

    /**
     * El preparador corrige un dato leído en el visor.
     *
     * @throws TransicionNoPermitida
     */
    public function corregir(DocumentoDato $dato, mixed $valor, string $motivo, User $preparador): void
    {
        if (trim($motivo) === '') {
            throw new TransicionNoPermitida(__('Escribe por qué corriges el dato.'));
        }

        $valor = is_string($valor) ? trim($valor) : $valor;
        $valor = $valor === '' ? null : $valor;

        if ($valor === $dato->valor) {
            return;
        }

        DB::transaction(function () use ($dato, $valor, $motivo, $preparador) {
            $dato->cambios()->create([
                'valor_anterior' => $dato->valor,
                'valor_nuevo' => $valor,
                'motivo' => trim($motivo),
                'actor_id' => $preparador->id,
            ]);

            $dato->update([
                'valor' => $valor,
                'origen' => OrigenDato::Preparador,
                'corregido_por' => $preparador->id,
                'corregido_en' => now(),
                'motivo' => trim($motivo),
            ]);

            if ($dato->item !== null) {
                $this->checklist->reabrirValidacion($dato->item);
            }
        });

        // Lo corregido puede cerrar (o abrir) excepciones. El motor se pide aquí y no en el
        // constructor porque él mismo lee los datos con esta clase.
        $entrevista = $dato->item?->entrevista;

        if ($entrevista !== null) {
            app(MotorReglas::class)->correr($entrevista);
        }
    }

    /**
     * El preparador corrige un renglón de los registros (F6 6.3b): el monto, la
     * categoría (la línea del Schedule C) o el sentido (por ejemplo, marcarlo
     * `personal` o `transferencia`). Cada campo que cambia deja su fila en el
     * historial; el valor leído no se borra. Si el renglón pasa a una categoría
     * y no se dice el sentido, el sentido sale del lado de la categoría
     * (ingreso para la línea 1; gasto para el resto). Reabre la validación del
     * documento y rehace las reglas, así las sumas se rehacen solas.
     *
     * @param  array{monto?: string, categoria?: string|null, sentido?: SentidoRenglon}  $cambios
     *
     * @throws TransicionNoPermitida
     */
    public function corregirRenglon(DocumentoRenglon $renglon, array $cambios, string $motivo, User $preparador): void
    {
        if (trim($motivo) === '') {
            throw new TransicionNoPermitida(__('Escribe por qué corriges el renglón.'));
        }

        $nuevos = [];

        if (array_key_exists('monto', $cambios)) {
            $monto = trim((string) $cambios['monto']);

            if (preg_match('/^\d+(\.\d{1,2})?$/', $monto) !== 1) {
                throw new TransicionNoPermitida(__('El monto debe ser un número positivo con hasta dos decimales.'));
            }

            $nuevos['monto'] = str_contains($monto, '.') ? str_pad($monto, strpos($monto, '.') + 3, '0') : $monto.'.00';
        }

        if (array_key_exists('categoria', $cambios)) {
            $categoria = $cambios['categoria'];

            if ($categoria !== null && ! in_array($categoria, ResultadoSumas::LINEAS, true)) {
                throw new TransicionNoPermitida(__('Esa categoría no es una línea del Schedule C.'));
            }

            $nuevos['categoria'] = $categoria;
            $nuevos['sentido'] = $cambios['sentido'] ?? match (true) {
                $categoria === null => SentidoRenglon::NoSe,
                $categoria === '1-sin-formulario' => SentidoRenglon::Ingreso,
                default => SentidoRenglon::Gasto,
            };
        } elseif (array_key_exists('sentido', $cambios)) {
            $nuevos['sentido'] = $cambios['sentido'];
        }

        $actuales = ['monto' => $renglon->monto, 'categoria' => $renglon->categoria, 'sentido' => $renglon->sentido];
        $cambiados = array_filter($nuevos, fn (mixed $nuevo, string $campo) => $nuevo !== $actuales[$campo], ARRAY_FILTER_USE_BOTH);

        if ($cambiados === []) {
            return;
        }

        DB::transaction(function () use ($renglon, $cambiados, $actuales, $motivo, $preparador) {
            foreach ($cambiados as $campo => $nuevo) {
                $renglon->cambios()->create([
                    'campo' => $campo,
                    'valor_anterior' => $actuales[$campo] instanceof SentidoRenglon ? $actuales[$campo]->value : $actuales[$campo],
                    'valor_nuevo' => $nuevo instanceof SentidoRenglon ? $nuevo->value : $nuevo,
                    'motivo' => trim($motivo),
                    'actor_id' => $preparador->id,
                ]);
            }

            $renglon->update([
                ...$cambiados,
                ...(array_key_exists('categoria', $cambiados) ? ['categoria_origen' => OrigenCategoria::Preparador] : []),
                'origen' => OrigenDato::Preparador,
                'corregido_por' => $preparador->id,
                'corregido_en' => now(),
                'motivo' => trim($motivo),
            ]);

            if ($renglon->item !== null) {
                $this->checklist->reabrirValidacion($renglon->item);
            }
        });

        $entrevista = $renglon->item?->entrevista;

        if ($entrevista !== null) {
            app(MotorReglas::class)->correr($entrevista);
        }
    }
}
