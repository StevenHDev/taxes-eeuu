<?php

namespace App\Services\WhatsappAgent;

use App\Models\User;
use App\Services\AgenteToolService;
use App\Services\BaseConocimientoService;
use App\Services\Consentimiento\EnlaceActivacion;
use App\Support\TelefonoWhatsapp;
use Illuminate\Support\Facades\Validator;

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
        private readonly BaseConocimientoService $baseConocimiento,
        private readonly EnlaceActivacion $enlaceActivacion,
        private readonly HerramientasEntrevista $entrevista,
    ) {}

    /**
     * @param  array<string, mixed>  $argumentos  ya decodificados del JSON que entrega OpenAI
     * @param  ?string  $telefono  el de esta conversación (ver AgenteConversacionalService) — solo lo
     *                             usa crear_cliente_taxes, para que la cuenta recién creada quede
     *                             vinculada por teléfono desde el primer momento, no solo por
     *                             WhatsappControl.cliente_id (ver User::phone() y su normalización).
     * @return array<string, mixed> resultado a incluir en el historial como respuesta de la tool
     */
    public function ejecutar(
        string $nombreTool,
        array $argumentos,
        ?User $cliente,
        User $actor,
        ?string $telefono = null,
    ): array {
        if ($nombreTool === 'think') {
            return ['ok' => true];
        }

        if ($nombreTool === 'crear_cliente_taxes') {
            return $this->crearClienteTaxes($argumentos, $actor, $telefono);
        }

        if ($cliente === null) {
            return ['error' => "La tool {$nombreTool} requiere una cuenta ya creada."];
        }

        return match ($nombreTool) {
            'consultar_base_conocimiento' => ['resultados' => $this->baseConocimiento->buscar((string) ($argumentos['consulta'] ?? ''))],
            'enviar_enlace_activacion' => $this->enviarEnlaceActivacion($cliente, $telefono),
            'consultar_entrevista' => $this->entrevista->consultar($cliente),
            'guardar_respuestas' => $this->entrevista->guardar($cliente, $argumentos, $actor),
            'agregar_persona' => $this->entrevista->agregarPersona($cliente, $argumentos, $actor),
            default => ['error' => "Tool desconocida: {$nombreTool}"],
        };
    }

    /**
     * F2.4: un enlace nuevo para crear la contraseña, solo si la cuenta
     * todavía no tiene contraseña y la conversación es
     * desde el mismo número de la cuenta. Con la contraseña ya definida
     * nunca se manda nada: cambiarla va por "¿Olvidaste tu contraseña?" y el
     * correo (así quien tome el WhatsApp del cliente no puede cambiarla).
     *
     * @return array<string, mixed>
     */
    private function enviarEnlaceActivacion(User $cliente, ?string $telefono): array
    {
        if ($telefono === null || $cliente->phone === null || TelefonoWhatsapp::normalizar($telefono) !== $cliente->phone) {
            return ['enviado' => false, 'motivo' => 'Los enlaces de activación solo se mandan al WhatsApp registrado en la cuenta.'];
        }

        $enlace = $this->enlaceActivacion->para($cliente);

        if ($enlace === null) {
            return ['enviado' => false, 'motivo' => 'La cuenta ya tiene contraseña. Si la olvidó, que use "¿Olvidaste tu contraseña?" en la pantalla de inicio de sesión del portal; le llega un correo. Nunca mandes enlaces para cambiarla.'];
        }

        return [
            'enviado' => true,
            'enlace' => $enlace,
            'vence_en_minutos' => EnlaceActivacion::MINUTOS,
            'instruccion' => 'Incluye el enlace tal cual en tu respuesta. Sirve para crear la contraseña y entrar al portal.',
        ];
    }

    /**
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    private function crearClienteTaxes(array $argumentos, User $actor, ?string $telefono): array
    {
        $email = (string) ($argumentos['email'] ?? '');

        // Encontrado en producción (2026-09-24, conversación con
        // +573213445027): un correo con un carácter de control colado (ej.
        // un artefacto de copiar/pegar) se guardaba tal cual, sin que nada lo
        // notara hasta que BienvenidaClientePortal fallaba en la cola —
        // dejando al cliente con una cuenta creada pero sin ninguna forma de
        // ponerle contraseña, porque a propósito nunca se manda por otro
        // canal (ver App\Notifications\BienvenidaClientePortal). Se valida
        // ACÁ, antes de crear nada, para que sea recuperable dentro de la
        // misma conversación en vez de un fallo silencioso en segundo plano.
        if (Validator::make(['email' => $email], ['email' => ['required', 'email']])->fails()) {
            return ['error' => 'El correo no es válido — pídele al cliente que lo confirme o corrija antes de volver a intentar.'];
        }

        // Incluye los archivados (F0.3): su correo sigue ocupado y crear otra
        // cuenta con él fallaría en la base.
        if (User::withTrashed()->where('email', $email)->exists()) {
            return ['error' => 'Ya existe una cuenta con ese correo. No crees otra: dile al cliente que el equipo de GlobalTax revisará su cuenta para darle acceso.'];
        }

        $cliente = $this->tools->crearCliente([
            'name' => $argumentos['nombre'] ?? null,
            'email' => $email,
            'phone' => $telefono,
        ], $actor);

        return ['cliente_id' => $cliente->id];
    }
}
