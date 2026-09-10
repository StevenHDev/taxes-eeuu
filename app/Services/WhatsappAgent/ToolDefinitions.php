<?php

namespace App\Services\WhatsappAgent;

use App\Enums\FaseConversacion;
use App\Enums\TaxForm;

/**
 * Esquema de las 5 tools del agente (más `think`) en formato function-calling
 * de OpenAI, filtrado por fase vigente vía paraFase() — así el modelo nunca
 * ve una tool que no le corresponde en ese momento (ver decisión de
 * arquitectura en docs/implementar_agente_n8n.md: "solo expone al modelo las
 * tools válidas para esa fase").
 *
 * A diferencia del diseño anterior de n8n, ninguna tool recibe `cliente_id`
 * ni (salvo `declarar_formas_cliente`, que es quien lo establece) `tax_year`
 * como parámetro — ToolExecutor ya conoce al cliente de la conversación
 * (resuelto por teléfono) y deriva el año fiscal vigente de las formas ya
 * declaradas, igual que EstadoConversacionResolver. Menos parámetros que el
 * modelo tiene que recordar y pasar correctamente turno a turno, mismo
 * principio de "el código deriva el estado, el modelo no lo carga en memoria".
 */
class ToolDefinitions
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function paraFase(FaseConversacion $fase): array
    {
        return match ($fase) {
            FaseConversacion::VerificacionCuenta => [self::crearClienteTaxes(), self::think()],
            FaseConversacion::DeterminacionFormas => [self::declararFormasCliente(), self::think()],
            FaseConversacion::Recoleccion => [
                self::declararFormasCliente(),
                self::consultarPendientesCliente(),
                self::consultarDocumentosExtra(),
                self::guardarCampoCliente(),
                self::think(),
            ],
            FaseConversacion::Cierre => [self::think()],
        };
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
    private static function declararFormasCliente(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'declarar_formas_cliente',
                'description' => 'Declara la(s) forma(s) del IRS aplicables al cliente para el año fiscal ya confirmado con él. Se invoca al cerrar la determinación de forma(s), y de nuevo cada vez que el cliente confirme una situación adicional más adelante en la conversación (con la lista actualizada — es seguro invocarla de nuevo, no borra el progreso ya guardado).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tax_year' => ['type' => 'integer', 'description' => 'Año fiscal ya confirmado explícitamente con el cliente (nunca asumido).'],
                        'formas_aplicables' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => array_map(fn (TaxForm $f) => $f->value, TaxForm::cases())],
                            'description' => 'Una o más formas del IRS aplicables, según el árbol de determinación.',
                        ],
                    ],
                    'required' => ['tax_year', 'formas_aplicables'],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function consultarPendientesCliente(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'consultar_pendientes_cliente',
                'description' => 'Qué le falta al cliente por entregar. Úsala cada vez que necesites saber qué campo o documento pedir a continuación, incluyendo la primera vez que entras a la fase de recolección y después de cada guardar_campo_cliente exitoso.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function consultarDocumentosExtra(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'consultar_documentos_extra',
                'description' => 'Catálogo de documentos opcionales que no forman parte de consultar_pendientes_cliente. Úsala únicamente cuando el cliente suba o mencione un documento/dato que no coincide con el campo actualmente pedido, Y ya haya confirmado que efectivamente quiso entregar algo distinto — nunca de forma preventiva.',
                'parameters' => ['type' => 'object', 'properties' => new \stdClass, 'required' => []],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function guardarCampoCliente(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'guardar_campo_cliente',
                'description' => 'Guarda un campo o documento que el cliente entregó. Nunca la invoques para un dato que el cliente no haya proporcionado literalmente en su mensaje actual o en uno anterior de esta misma conversación (salvo por una relación documento→campo que ya trajo `revela`).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'forma' => ['type' => 'string', 'description' => 'La `forma` exacta de la entrada correspondiente en consultar_pendientes_cliente/consultar_documentos_extra — nunca la infieras.'],
                        'campo' => ['type' => 'string', 'description' => 'Clave del campo, tal cual la trae el catálogo.'],
                        'tipo_campo' => ['type' => 'string', 'enum' => ['documento', 'dato', 'mixto'], 'description' => 'Tal cual lo trae el catálogo para ese campo.'],
                        'modo' => ['type' => 'string', 'enum' => ['archivo', 'texto', 'no_aplica'], 'description' => '"archivo" para un documento, "texto" para un dato tecleado, "no_aplica" si el cliente confirmó que no lo tiene (solo campos opcionales).'],
                        'tipo_dato' => ['type' => ['string', 'null'], 'enum' => ['string', 'number', 'object', 'array_string', 'array_object', null], 'description' => 'Requerido cuando modo="texto"; tal cual lo trae el catálogo.'],
                        'contenido' => ['description' => 'El valor entregado por el cliente. Siempre como string, incluso para una estructura compleja (objeto/arreglo serializado).'],
                        'acumular' => ['type' => 'boolean', 'description' => 'true si este documento debe sumarse a un valor ya guardado en vez de reemplazarlo (ver `revela.acumulable`).'],
                        'subcampo' => ['type' => ['string', 'null'], 'description' => 'Solo junto con acumular=true sobre un campo tipo objeto: qué subcampo aporta este documento.'],
                        'nombre_original' => ['type' => ['string', 'null'], 'description' => 'Nombre original del archivo, si aplica.'],
                        'revelados' => [
                            'type' => 'array',
                            'description' => 'Campos que este mismo documento ya reveló (ver `revela` de la última consulta de pendientes), para guardarlos en la misma invocación.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'forma' => ['type' => 'string'],
                                    'campo' => ['type' => 'string'],
                                    'tipo_campo' => ['type' => 'string', 'enum' => ['documento', 'dato', 'mixto']],
                                    'tipo_dato' => ['type' => 'string', 'enum' => ['string', 'number', 'object', 'array_string', 'array_object']],
                                    'contenido' => ['description' => 'Siempre como string, igual que el campo raíz.'],
                                    'acumular' => ['type' => 'boolean'],
                                    'subcampo' => ['type' => ['string', 'null']],
                                ],
                                'required' => ['forma', 'campo', 'tipo_campo', 'tipo_dato', 'contenido'],
                            ],
                        ],
                    ],
                    'required' => ['forma', 'campo', 'tipo_campo', 'modo'],
                ],
            ],
        ];
    }
}
