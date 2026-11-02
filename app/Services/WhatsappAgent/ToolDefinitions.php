<?php

namespace App\Services\WhatsappAgent;

use App\Enums\FaseConversacion;
use App\Support\AgenteToolEstados;

/**
 * Esquema de las tools del agente (más `think`) en formato function-calling
 * de OpenAI, filtrado por fase vigente vía paraFase() — así el modelo nunca
 * ve una tool que no le corresponde en ese momento (ver decisión de
 * arquitectura en docs/implementar_agente_n8n.md: "solo expone al modelo las
 * tools válidas para esa fase").
 *
 * Ninguna tool recibe `cliente_id` ni el año fiscal: ToolExecutor ya conoce
 * al cliente de la conversación (resuelto por teléfono) y la entrevista es
 * siempre la del año fiscal actual (F2). "El código deriva el estado, el
 * modelo no lo carga en memoria".
 */
class ToolDefinitions
{
    /**
     * Catálogo completo de tools de la fase (para uso administrativo, ver
     * AgenteToolController) — nunca filtra por AgenteToolEstados, así el
     * panel siempre puede mostrar y reactivar una tool desactivada.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function paraFase(FaseConversacion $fase): array
    {
        return match ($fase) {
            FaseConversacion::VerificacionCuenta => [self::crearClienteTaxes(), self::think()],
            // El chat del portal solo responde dudas: la entrevista se
            // responde en sus pantallas (ver prompt_actuales/fases/portal_dudas.md).
            FaseConversacion::PortalDudas => [
                self::consultarEntrevista(),
                self::consultarBaseConocimiento(),
                self::think(),
            ],
            // F2.8: nunca reciben archivos ni datos sensibles: eso va por el
            // portal.
            FaseConversacion::Consentimiento => [
                self::enviarEnlaceActivacion(),
                self::consultarBaseConocimiento(),
                self::think(),
            ],
            FaseConversacion::Entrevista => [
                self::consultarEntrevista(),
                self::guardarRespuestas(),
                self::agregarPersona(),
                self::consultarBaseConocimiento(),
                self::think(),
            ],
            FaseConversacion::Seguimiento => [
                self::consultarEntrevista(),
                self::consultarBaseConocimiento(),
                self::think(),
            ],
        };
    }

    /**
     * Igual que paraFase(), pero descartando las tools desactivadas para
     * esta fase desde el panel de administración (AgenteToolEstados) — es
     * la que realmente ve el modelo en cada turno (ver
     * AgenteConversacionalService). `think` nunca se ofrece en el panel para
     * desactivar, así que siempre queda incluida.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function habilitadasParaFase(FaseConversacion $fase): array
    {
        return array_values(array_filter(
            self::paraFase($fase),
            fn (array $tool) => AgenteToolEstados::activo($fase, $tool['function']['name']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function think(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'think',
                'description' => 'Espacio de razonamiento antes de actuar — no tiene efecto real, no modifica ningún dato ni consulta nada. Invócala siempre antes de decidir qué preguntar o qué tool usar.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'razonamiento' => ['type' => 'string', 'description' => 'Tu razonamiento en texto libre antes de actuar.'],
                    ],
                    'required' => ['razonamiento'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function crearClienteTaxes(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'crear_cliente_taxes',
                'description' => 'Crea la cuenta del cliente en GlobalTax. Se invoca una única vez por conversación, solo cuando el cliente confirmó no tener cuenta y ya entregó nombre y email.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'nombre' => ['type' => 'string', 'description' => 'Nombre proporcionado por el cliente, tal cual (puede ser parcial — no lo completes ni lo corrijas).'],
                        'email' => ['type' => 'string', 'description' => 'Correo proporcionado por el cliente, tal cual.'],
                    ],
                    'required' => ['nombre', 'email'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function enviarEnlaceActivacion(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'enviar_enlace_activacion',
                'description' => 'Pide un enlace nuevo para que el cliente cree la contraseña de su cuenta del portal, cuando el que le mandaste venció o no le funciona. El enlace lo arma el sistema: inclúyelo tal cual si la respuesta trae "enviado": true. Si trae "enviado": false, explícale el motivo; nunca inventes un enlace. Nunca sirve para cambiar una contraseña que ya existe.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function consultarEntrevista(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'consultar_entrevista',
                'description' => 'Estado de la entrevista del cliente: las próximas preguntas que se pueden hacer por WhatsApp (código, texto, tipo, opciones y persona), el avance por pantalla, cuántas preguntas sensibles y documentos faltan en el portal, y los enlaces exactos al portal. Úsala al empezar cada turno de entrevista y cuando el cliente pregunte cómo va lo suyo. Copia los enlaces tal cual; nunca los inventes.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function guardarRespuestas(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'guardar_respuestas',
                'description' => 'Guarda una o varias respuestas de la entrevista en un solo llamado (por ejemplo, "trabajo en Uber y mi esposa en Walmart" son varias casillas a la vez). Usa solo códigos que te dio consultar_entrevista y valores que el cliente dijo con claridad: "si"/"no"; para usted/cónyuge una lista con "usted" y/o "conyuge", o "no"; para opciones, el valor exacto de la opción; fechas AAAA-MM-DD; números sin texto. Si una respuesta vuelve en `rechazadas`, no la adivines: pregúntale al cliente de nuevo. Nunca guardes datos sensibles: el sistema los rechaza.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'respuestas' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'codigo' => ['type' => 'string', 'description' => 'Código de la pregunta, por ejemplo D1.'],
                                    'persona_id' => ['type' => 'integer', 'description' => 'Solo en las preguntas de una persona del bloque C.'],
                                    'valor' => ['description' => 'La respuesta con la forma que pide su tipo.'],
                                ],
                                'required' => ['codigo', 'valor'],
                            ],
                        ],
                    ],
                    'required' => ['respuestas'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function agregarPersona(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'agregar_persona',
                'description' => 'Agrega una persona que vivió con el cliente o que mantuvo (bloque C), después de guardar C0 = "si". Devuelve su persona_id para guardar sus respuestas. Si ya sabes el parentesco, pásalo con uno de los valores de C3.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'parentesco' => ['type' => 'string', 'enum' => ['hijo', 'hijastro', 'hijo_crianza', 'hermano', 'nieto', 'padre', 'ninguno']],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function consultarBaseConocimiento(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'consultar_base_conocimiento',
                'description' => 'Búsqueda de texto libre sobre la base de conocimiento cargada por un administrador (documentación general de impuestos). Úsala cuando el cliente pregunte algo general que no depende de sus datos puntuales — nunca para preguntas sobre el estado de SU declaración, que se resuelven con consultar_entrevista.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'consulta' => ['type' => 'string', 'description' => 'Términos de búsqueda — palabras clave de lo que el cliente preguntó, no la pregunta completa tal cual.'],
                    ],
                    'required' => ['consulta'],
                ],
            ],
        ];
    }
}
