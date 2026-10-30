<?php

namespace App\Services\WhatsappAgent;

use App\Enums\Entrevista\CanalRespuesta;
use App\Models\Entrevista\Entrevista;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use App\Services\Entrevista\DocumentosEntrevista;
use App\Services\Entrevista\MotorEntrevista;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaData;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\RespuestaRechazada;

/**
 * Las tools del agente sobre la entrevista nueva (F2.8): todas leen el
 * estado de MotorEntrevista y escriben solo con RespuestaGuardador, canal
 * `whatsapp`. Nunca devuelven valores sensibles ni datos de documentos; los
 * enlaces al portal los arma el código, no el modelo.
 */
class HerramientasEntrevista
{
    /** Cuántas preguntas devuelve por vez, para que el modelo agrupe sin perderse. */
    private const PROXIMAS = 25;

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
        if (! $cliente->usaEntrevistaCatalogo()) {
            return ['error' => 'Este cliente todavía usa el formulario actual: usa consultar_pendientes_cliente.'];
        }

        $entrevista = $this->entrevista($cliente);
        $situacion = $this->motor->situacion($entrevista);
        $preguntas = $this->catalogo->preguntas($entrevista->tax_year)->keyBy('codigo');
        $personas = $entrevista->personas()->get()->keyBy('id');
        $documentos = $this->documentos->lista($entrevista, $situacion, false);

        return [
            'estado' => $entrevista->estado->value,
            'puede_preguntar_por_whatsapp' => EstadoConversacionResolver::entrevistaPorWhatsapp($cliente),
            'proximas_preguntas' => collect($situacion->pendientesParaWhatsapp())
                ->take(self::PROXIMAS)
                ->map(function (array $p) use ($preguntas, $personas, $entrevista) {
                    $pregunta = $preguntas[$p['codigo']];
                    $persona = $p['persona_id'] === null ? null : $personas->get($p['persona_id']);

                    return [
                        'codigo' => $p['codigo'],
                        'pantalla' => $p['pantalla'],
                        'texto' => str_replace('{anio}', (string) $entrevista->tax_year, $pregunta['texto_es']),
                        'texto_en' => $pregunta['texto_en'] === null ? null : str_replace('{anio}', (string) $entrevista->tax_year, $pregunta['texto_en']),
                        'tipo' => $pregunta['tipo_respuesta'],
                        'opciones' => collect($pregunta['opciones'] ?? [])->pluck('valor')->all(),
                        'persona' => $persona === null ? null : ['persona_id' => $persona->id, 'numero' => $persona->orden, 'etiqueta' => $persona->etiqueta],
                    ];
                })->values()->all(),
            'avance' => $this->pasos->avance($situacion),
            'falta_en_el_portal' => [
                'preguntas_sensibles' => count($situacion->faltaEnPortal()),
                'documentos_pendientes' => collect($documentos)->where('archivos', [])->count(),
                'identidad_pendiente' => collect($situacion->pantallas[1]['preguntas'] ?? [])->contains('respondida', false),
            ],
            'enlaces' => [
                'entrevista' => route('portal.entrevista.inicio'),
                'documentos' => route('portal.entrevista.documentos'),
                'revisar_y_confirmar' => route('portal.entrevista.resumen'),
            ],
        ];
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
        if (! $cliente->usaEntrevistaCatalogo() || ! EstadoConversacionResolver::entrevistaPorWhatsapp($cliente)) {
            return ['error' => 'La entrevista por WhatsApp no está habilitada para este cliente: manda el enlace del portal.'];
        }

        $respuestas = $argumentos['respuestas'] ?? null;

        if (! is_array($respuestas) || $respuestas === []) {
            return ['error' => 'Falta la lista `respuestas`.'];
        }

        $entrevista = $this->entrevista($cliente);
        $guardadas = [];
        $rechazadas = [];

        foreach ($respuestas as $r) {
            $codigo = is_array($r) ? (string) ($r['codigo'] ?? '') : '';
            $personaId = is_array($r) && isset($r['persona_id']) ? (int) $r['persona_id'] : null;

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
        if (! $cliente->usaEntrevistaCatalogo() || ! EstadoConversacionResolver::entrevistaPorWhatsapp($cliente)) {
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

    private function entrevista(User $cliente): Entrevista
    {
        return $this->guardador->entrevistaDe($cliente, CanalRespuesta::Whatsapp)->fresh();
    }
}
