<?php

namespace App\Services\WhatsappAgent;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoDocumento;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\RespuestaRechazada;
use Illuminate\Support\Collection;

/**
 * Las tools del agente sobre la entrevista nueva (F2.8): todas leen el
 * estado de MotorEntrevista y escriben solo con RespuestaGuardador, canal
 * `whatsapp`. Nunca devuelven valores sensibles ni datos de documentos; los
 * enlaces al portal los arma el código, no el modelo.
 */
class HerramientasEntrevista
{
    /** Cuántas preguntas le llegan al agente por consulta: un mensaje con más se siente como un formulario. */
    private const PROXIMAS = 8;

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly MotorEntrevista $motor,
        private readonly PasosEntrevista $pasos,
        private readonly RespuestaGuardador $guardador,
        private readonly DocumentosEntrevista $documentos,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function consultar(User $cliente): array
    {
        $entrevista = $this->entrevista($cliente);
        $situacion = $this->motor->situacion($entrevista);
        $preguntas = $this->catalogo->preguntas($entrevista->tax_year)->keyBy('codigo');
        $personas = $entrevista->personas()->get()->keyBy('id');
        $documentos = $this->documentos->lista($entrevista, $situacion, false);
        $todas = $situacion->pendientesParaWhatsapp();
        $pendientes = $this->conSeguimientosPrimero($todas, $preguntas);

        return [
            'estado' => $entrevista->estado->value,
            'puede_preguntar_por_whatsapp' => EstadoConversacionResolver::entrevistaPorWhatsapp($cliente),
            // Sin cónyuge (B1 distinto de "casado"), las preguntas "usted o
            // cónyuge" se hacen como sí o no y "sí" se guarda como ["usted"].
            'con_conyuge' => $this->pasos->conConyuge($situacion),
            'proximas_preguntas' => collect($pendientes)
                ->take(self::PROXIMAS)
                ->map(function (array $p) use ($preguntas, $personas, $entrevista) {
                    $pregunta = $preguntas[$p['codigo']];
                    $persona = $p['persona_id'] === null ? null : $personas->get($p['persona_id']);

                    return [
                        'codigo' => $p['codigo'],
                        'pantalla' => $p['pantalla'],
                        'texto' => str_replace('{anio}', (string) $entrevista->tax_year, $pregunta['texto_es']),
                        'texto_en' => $pregunta['texto_en'] === null ? null : str_replace('{anio}', (string) $entrevista->tax_year, $pregunta['texto_en']),
                        // Lo que aclara la pregunta (qué incluye y qué no): el agente la usa para no confundirse con el cliente.
                        'ayuda' => empty($pregunta['ayuda_es']) ? null : str_replace('{anio}', (string) $entrevista->tax_year, $pregunta['ayuda_es']),
                        'tipo' => $pregunta['tipo_respuesta'],
                        'opciones' => collect($pregunta['opciones'] ?? [])->pluck('valor')->all(),
                        'persona' => $persona === null ? null : ['persona_id' => $persona->id, 'numero' => $persona->orden, 'etiqueta' => $persona->etiqueta],
                    ];
                })->values()->all(),
            // Cuántas preguntas más quedan después de estas (de esta y de las otras pantallas), para que el agente sepa que sigue otro mensaje.
            'quedan_mas' => count($todas) - min(count($pendientes), self::PROXIMAS),
            'avance' => $this->pasos->avance($situacion),
            'falta_en_el_portal' => [
                'preguntas_sensibles' => count($situacion->faltaEnPortal()),
                'documentos_pendientes' => collect($documentos)->where('resuelto', false)->count(),
                'identidad_pendiente' => collect($situacion->pantallas[1]['preguntas'] ?? [])->contains('respondida', false),
            ],
            'documentos' => $this->resumenDocumentos($documentos, $personas),
            'enlaces' => [
                'entrevista' => route('portal.entrevista.inicio'),
                'documentos' => route('portal.entrevista.documentos'),
                'revisar_y_confirmar' => route('portal.entrevista.resumen'),
            ],
        ];
    }

    /**
     * Las preguntas que salen de un "sí" anterior (seguimientos) van antes que las
     * de la pantalla siguiente: se pregunta lo que el cliente acaba de abrir. Y cada
     * consulta trae preguntas de **una sola pantalla** (la de la primera pendiente),
     * aunque sean menos de 8: así cada mensaje es de un tema y puede abrir con una
     * frase natural, en vez de mezclar visa, criptomonedas e ingresos. Dentro de cada
     * grupo se mantiene el orden del catálogo.
     *
     * @param  list<array{codigo: string, persona_id: int|null, pantalla: int}>  $pendientes
     * @param  Collection<string, array<string, mixed>>  $preguntas
     * @return list<array{codigo: string, persona_id: int|null, pantalla: int}>
     */
    private function conSeguimientosPrimero(array $pendientes, Collection $preguntas): array
    {
        $esSeguimiento = fn (array $p) => ($preguntas[$p['codigo']]['nivel'] ?? null) === 'seguimiento';

        $ordenadas = [
            ...array_values(array_filter($pendientes, $esSeguimiento)),
            ...array_values(array_filter($pendientes, fn (array $p) => ! $esSeguimiento($p))),
        ];

        if ($ordenadas === []) {
            return [];
        }

        $pantalla = $ordenadas[0]['pantalla'];

        return array_values(array_filter($ordenadas, fn (array $p) => $p['pantalla'] === $pantalla));
    }

    /**
     * Guarda varias respuestas de una vez, en el orden en que llegan (así
     * un seguimiento abierto por una respuesta anterior ya se puede guardar
     * en la misma llamada). Cada una se valida por separado: lo rechazado no
     * se escribe y vuelve con el motivo para que el agente pregunte de nuevo.
     *
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    public function guardar(User $cliente, array $argumentos, User $actor): array
    {
        if (! EstadoConversacionResolver::entrevistaPorWhatsapp($cliente)) {
            return ['error' => 'La entrevista por WhatsApp no está habilitada para este cliente: manda el enlace del portal.'];
        }

        $respuestas = $argumentos['respuestas'] ?? null;

        if (! is_array($respuestas) || $respuestas === []) {
            return ['error' => 'Falta la lista `respuestas`.'];
        }

        $entrevista = $this->entrevista($cliente);
        $preguntas = $this->catalogo->preguntas($entrevista->tax_year)->keyBy('codigo');
        $guardadas = [];
        $rechazadas = [];

        foreach ($respuestas as $r) {
            $codigo = is_array($r) ? (string) ($r['codigo'] ?? '') : '';
            $personaId = is_array($r) && isset($r['persona_id']) ? (int) $r['persona_id'] : null;

            // La pantalla 1 (identidad: nombre, nacimiento, ocupación, dirección) es solo del portal: por aquí
            // nunca se pregunta, así que un código de esa pantalla es un código equivocado (el agente guardó
            // "nunca casado" como nombre y "no" como dirección).
            if (($preguntas->get($codigo)['pantalla'] ?? 2) < 2) {
                $rechazadas[] = ['codigo' => $codigo, 'motivo' => 'Código equivocado: esa pregunta no se hace por WhatsApp. No se lo menciones al cliente ni le repitas la pregunta: llama a consultar_entrevista y guarda con el `codigo` exacto de proximas_preguntas.'];

                continue;
            }

            try {
                $this->guardador->guardar($entrevista->fresh(), new RespuestaData($codigo, $r['valor'] ?? null, $personaId), CanalRespuesta::Whatsapp, $actor);
                $guardadas[] = $codigo;
            } catch (RespuestaRechazada $e) {
                $rechazadas[] = ['codigo' => $codigo, 'motivo' => $e->getMessage()];
            }
        }

        return [
            'guardadas' => $guardadas,
            'rechazadas' => $rechazadas,
            'quedan_por_whatsapp' => count($this->motor->situacion($entrevista->fresh())->pendientesParaWhatsapp()),
        ];
    }

    /**
     * Agrega una persona del bloque C. Si viene el parentesco, lo guarda
     * como su C3.
     *
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    public function agregarPersona(User $cliente, array $argumentos, User $actor): array
    {
        if (! EstadoConversacionResolver::entrevistaPorWhatsapp($cliente)) {
            return ['error' => 'La entrevista por WhatsApp no está habilitada para este cliente: manda el enlace del portal.'];
        }

        $entrevista = $this->entrevista($cliente);

        if (! $this->pasos->conPersonas($this->motor->situacion($entrevista))) {
            return ['error' => 'Primero guarda C0 = "si" con guardar_respuestas: el bloque C se abre con esa respuesta.'];
        }

        $persona = $this->guardador->agregarPersona($entrevista);
        $resultado = ['persona_id' => $persona->id, 'numero' => $persona->orden];

        if (filled($argumentos['parentesco'] ?? null)) {
            try {
                $this->guardador->guardar($entrevista->fresh(), new RespuestaData('C3', $argumentos['parentesco'], $persona->id), CanalRespuesta::Whatsapp, $actor);
                $resultado['parentesco_guardado'] = true;
            } catch (RespuestaRechazada $e) {
                $resultado['parentesco_guardado'] = false;
                $resultado['motivo'] = $e->getMessage();
            }
        }

        return $resultado;
    }

    /**
     * Lo que el agente necesita para explicar qué documentos faltan (F3.5):
     * nombre sencillo, por qué se pide, quién lo manda, cuándo llega y qué
     * hacer si no llega, más lo que dijo el preparador si lo devolvió. Nunca
     * archivos, nombres de archivo ni enlaces a ellos.
     *
     * @param  list<array<string, mixed>>  $documentos  filas de DocumentosEntrevista::lista()
     * @param  Collection<int, EntrevistaPersona>  $personas
     * @return array<string, mixed>
     */
    private function resumenDocumentos(array $documentos, Collection $personas): array
    {
        $faltan = collect($documentos)->where('resuelto', false);
        $devueltos = [EstadoDocumento::ConObservacion->value, EstadoDocumento::NoAplicaRechazado->value];

        return [
            'total' => count($documentos),
            'faltan' => $faltan->count(),
            'devueltos_por_el_preparador' => $faltan->whereIn('estado', $devueltos)->count(),
            'no_se_pudo_leer' => $faltan->where('estado', EstadoDocumento::NoSePudoLeer->value)->count(),
            'en_revision' => collect($documentos)->where('resuelto', true)->whereIn('estado', [EstadoDocumento::Subido->value, EstadoDocumento::Leido->value, EstadoDocumento::NoSePudoLeer->value])->count(),
            'todo_listo' => $documentos !== [] && $faltan->isEmpty(),
            'pendientes' => $faltan->map(function (array $d) use ($personas, $devueltos) {
                $persona = $d['persona_id'] === null ? null : $personas->get($d['persona_id']);

                return [
                    'nombre' => $d['nombre'],
                    'nombre_oficial' => $d['nombre_oficial'],
                    'numero' => $d['numero'],
                    'persona' => $persona?->etiqueta,
                    'por_que' => $d['por_que'],
                    'quien_lo_envia' => $d['emisor'],
                    'fecha_llegada' => $d['fecha_llegada'],
                    'si_no_llega' => $d['si_no_llega'],
                    'que_subir' => $d['que_subir'],
                    'devuelto_por_el_preparador' => in_array($d['estado'], $devueltos, true),
                    'comentario_del_preparador' => in_array($d['estado'], $devueltos, true) ? $d['observacion'] : null,
                    // F4.8a: el archivo no se pudo leer; nunca llega lo leído, solo el aviso.
                    'no_se_pudo_leer' => $d['estado'] === EstadoDocumento::NoSePudoLeer->value,
                    'aviso_no_se_pudo_leer' => $d['estado'] === EstadoDocumento::NoSePudoLeer->value
                        ? 'No pudimos leer el archivo que subió: dile que lo suba de nuevo en "Mis documentos", más nítido o en PDF, y mándale el enlace de documentos.'
                        : null,
                ];
            })->values()->all(),
        ];
    }

    private function entrevista(User $cliente): Entrevista
    {
        return $this->guardador->entrevistaDe($cliente, CanalRespuesta::Whatsapp)->fresh();
    }
}
