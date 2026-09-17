<?php

namespace App\Services\WhatsappAgent;

use App\DataTransferObjects\EventoRecoleccionData;
use App\Enums\MetodoExtraccionDocumento;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\AgenteToolService;
use App\Services\BaseConocimientoService;
use App\Services\EventoRecoleccionService;
use App\Support\EventoValidator;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\UploadedFile;

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
        private readonly BaseConocimientoService $baseConocimiento,
    ) {}

    /**
     * @param  array<string, mixed>  $argumentos  ya decodificados del JSON que entrega OpenAI
     * @param  ?UploadedFile  $file  solo cuando este tool call es guardar_campo_cliente con
     *                               modo="archivo" — quien arma el turno (Fase 4: TwilioMediaDownloader
     *                               + el mensaje en curso) ya resolvió el archivo antes de llegar acá.
     * @param  ?MetodoExtraccionDocumento  $metodoExtraccion  qué nivel de DocumentoExtraccionService
     *                                                        resolvió el texto de `$file`, para dejarlo
     *                                                        registrado en el Documento resultante.
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
        ?UploadedFile $file = null,
        ?MetodoExtraccionDocumento $metodoExtraccion = null,
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
            'declarar_formas_cliente' => $this->declararFormasCliente($cliente, $argumentos),
            'consultar_pendientes_cliente' => $this->tools->pendientes($cliente, $this->taxYearVigente($cliente)),
            'consultar_documentos_extra' => $this->tools->documentosExtra($this->taxYearVigente($cliente)),
            'guardar_campo_cliente' => $this->guardarCampoCliente($cliente, $argumentos, $actor, $file, $metodoExtraccion),
            'consultar_base_conocimiento' => ['resultados' => $this->baseConocimiento->buscar((string) ($argumentos['consulta'] ?? ''))],
            default => ['error' => "Tool desconocida: {$nombreTool}"],
        };
    }

    /**
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    private function crearClienteTaxes(array $argumentos, User $actor, ?string $telefono): array
    {
        $cliente = $this->tools->crearCliente([
            'name' => $argumentos['nombre'] ?? null,
            'email' => $argumentos['email'] ?? null,
            'phone' => $telefono,
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
    private function guardarCampoCliente(
        User $cliente,
        array $argumentos,
        User $actor,
        ?UploadedFile $file,
        ?MetodoExtraccionDocumento $metodoExtraccion,
    ): array {
        $taxYear = $this->taxYearVigente($cliente);
        $datos = [...$argumentos, 'tax_year' => $taxYear, 'cliente_id' => $cliente->id];

        // El modelo siempre manda `contenido` (y cada revelados[].contenido)
        // como string, incluso para una estructura compleja (ver
        // ToolDefinitions) — decodificarlo es lo mismo que ya hace
        // EventoRequest::prepareForValidation() para el camino HTTP; ver
        // EventoValidator::decodificarContenido() para el bug real que
        // causaba omitir este paso acá.
        $datos = [...$datos, ...$this->eventoValidator->decodificarContenido($datos)];

        $errores = $this->eventoValidator->validar($datos, $file);

        if ($errores !== []) {
            // Se devuelve como resultado de la tool (no se lanza excepción): un
            // tool call inválido es una situación conversacional recuperable
            // — el modelo puede corregir el/los argumentos y reintentar en el
            // mismo turno, en vez de que el job entero falle.
            return ['error' => 'validacion', 'detalles' => $errores];
        }

        $resultado = $this->eventos->procesar(new EventoRecoleccionData($datos, $file, $actor));

        // Solo el camino de WhatsApp conoce el nivel que resolvió el texto del
        // documento (Fase 4) — un documento subido por el panel nunca trae
        // $metodoExtraccion, y su columna se queda null (ver la migración).
        if ($file !== null && $metodoExtraccion !== null && $resultado['campo_cliente']->documento_id !== null) {
            Documento::query()
                ->whereKey($resultado['campo_cliente']->documento_id)
                ->update(['metodo_extraccion' => $metodoExtraccion->value]);
        }

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
