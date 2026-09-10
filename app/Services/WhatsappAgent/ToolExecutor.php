<?php

namespace App\Services\WhatsappAgent;

use App\DataTransferObjects\EventoRecoleccionData;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\AgenteToolService;
use App\Services\EventoRecoleccionService;
use App\Support\EventoValidator;
use App\Support\TaxFieldCatalog;

/**
 * Despacha un tool call del agente conversacional a los servicios internos
 * correspondientes — nunca por HTTP (ver decisión de arquitectura en
 * docs/implementar_agente_n8n.md). `$cliente` es null solo durante la fase
 * VerificacionCuenta, antes de que exista cuenta.
 */
class ToolExecutor
{
    public function __construct(
        private readonly AgenteToolService $tools,
        private readonly EventoRecoleccionService $eventos,
        private readonly EventoValidator $eventoValidator,
    ) {}

    /**
     * @param  array<string, mixed>  $argumentos  ya decodificados del JSON que entrega OpenAI
     * @return array<string, mixed> resultado a incluir en el historial como respuesta de la tool
     */
    public function ejecutar(string $nombreTool, array $argumentos, ?User $cliente, User $actor): array
    {
        if ($nombreTool === 'think') {
            return ['ok' => true];
        }

        if ($nombreTool === 'crear_cliente_taxes') {
            return $this->crearClienteTaxes($argumentos, $actor);
        }

        if ($cliente === null) {
            return ['error' => "La tool {$nombreTool} requiere una cuenta ya creada."];
        }

        return match ($nombreTool) {
            'declarar_formas_cliente' => $this->declararFormasCliente($cliente, $argumentos),
            'consultar_pendientes_cliente' => $this->tools->pendientes($cliente, $this->taxYearVigente($cliente)),
            'consultar_documentos_extra' => $this->tools->documentosExtra($this->taxYearVigente($cliente)),
            'guardar_campo_cliente' => $this->guardarCampoCliente($cliente, $argumentos, $actor),
            default => ['error' => "Tool desconocida: {$nombreTool}"],
        };
    }

    /**
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    private function crearClienteTaxes(array $argumentos, User $actor): array
    {
        $cliente = $this->tools->crearCliente([
            'name' => $argumentos['nombre'] ?? null,
            'email' => $argumentos['email'] ?? null,
        ], $actor);

        return ['cliente_id' => $cliente->id];
    }

    /**
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    private function declararFormasCliente(User $cliente, array $argumentos): array
    {
        $taxYear = (int) ($argumentos['tax_year'] ?? 0);
        $formas = (array) ($argumentos['formas_aplicables'] ?? []);

        return $this->tools->declararFormas($cliente, $taxYear, $formas);
    }

    /**
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    private function guardarCampoCliente(User $cliente, array $argumentos, User $actor): array
    {
        $taxYear = $this->taxYearVigente($cliente);
        $datos = [...$argumentos, 'tax_year' => $taxYear, 'cliente_id' => $cliente->id];

        $errores = $this->eventoValidator->validar($datos);

        if ($errores !== []) {
            // Se devuelve como resultado de la tool (no se lanza excepción): un
            // tool call inválido es una situación conversacional recuperable
            // — el modelo puede corregir el/los argumentos y reintentar en el
            // mismo turno, en vez de que el job entero falle.
            return ['error' => 'validacion', 'detalles' => $errores];
        }

        $resultado = $this->eventos->procesar(new EventoRecoleccionData($datos, null, $actor));

        return [
            'campo' => $resultado['campo_cliente']->campo,
            'estado' => $resultado['campo_cliente']->estado,
            // Se repite acá el mismo `revela` que ya traía esta entrada en
            // consultar_pendientes_cliente — ver EventoController::store,
            // mismo principio de no depender de que el modelo lo recuerde de
            // un turno anterior.
            'revela' => TaxFieldCatalog::revelaPara($taxYear, $resultado['campo_cliente']->campo),
            'revelados' => collect($resultado['revelados'])->map(fn (array $r) => [
                'forma' => $r['campo_cliente']->forma,
                'campo' => $r['campo_cliente']->campo,
                'estado' => $r['campo_cliente']->estado,
            ])->all(),
        ];
    }

    /**
     * El año fiscal "en curso" es el mayor entre las formas ya declaradas del
     * cliente — mismo criterio que EstadoConversacionResolver (ver ahí por
     * qué no se persiste en ningún otro lado).
     */
    private function taxYearVigente(User $cliente): int
    {
        return (int) FormaCliente::query()->where('user_id', $cliente->id)->max('tax_year');
    }
}
