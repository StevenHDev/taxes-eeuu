<?php

namespace App\Http\Controllers;

use App\Enums\CatalogoFiscal\SeveridadRegla;
use App\Enums\EstadoControlConversacion;
use App\Enums\Expediente\EstadoExpediente;
use App\Enums\Expediente\PerfilExpediente;
use App\Enums\Lectura\EstadoExcepcion;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Http\Requests\ClienteStoreRequest;
use App\Models\Consentimiento\Consentimiento;
use App\Models\Entrevista\Entrevista;
use App\Models\Expediente\Expediente;
use App\Models\Expediente\ExpedienteEvento;
use App\Models\Lectura\Excepcion;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use App\Notifications\BienvenidaClientePortal;
use App\Services\Entrevista\ChecklistDocumentos;
use App\Services\Expediente\DiagnosticoExpediente;
use App\Services\Expediente\FichaCliente;
use App\Services\UsuarioRetencionService;
use App\Services\Whatsapp\WhatsappChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    use ManagesClientes;

    public function __construct(
        private readonly WhatsappChannel $canal,
        private readonly UsuarioRetencionService $retencion,
        private readonly ChecklistDocumentos $checklist,
        private readonly FichaCliente $ficha,
        private readonly DiagnosticoExpediente $diagnostico,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        // Colección completa (scopeada por rol): el filtrado, orden y paginado
        // se hacen client-side con el DataTable de TanStack en el navegador.
        $clientes = $this->clientesVisiblesPara($request->user())
            ->orderByDesc('created_at')
            ->get();

        $ids = $clientes->modelKeys();

        // Todo por cliente en consultas agrupadas, sin una por fila.
        $documentos = $this->checklist->resumenPorCliente($ids, $taxYear);
        $expedientes = Expediente::query()->where('tax_year', $taxYear)->whereIn('user_id', $ids)->get()->keyBy('user_id');
        $canales = Entrevista::query()->where('tax_year', $taxYear)->whereIn('user_id', $ids)->pluck('canal_inicio', 'user_id');
        $excepciones = $this->excepcionesPorCliente($ids, $taxYear);

        $clientes = $clientes
            ->map(function (User $cliente) use ($documentos, $expedientes, $canales, $excepciones) {
                $expediente = $expedientes->get($cliente->id);
                $estado = $expediente?->estado ?? EstadoExpediente::Invitado;

                return [
                    'id' => $cliente->id,
                    'name' => $cliente->name,
                    'email' => $cliente->email,
                    'phone' => $cliente->phone,
                    'es_prueba' => $cliente->es_prueba,
                    'expediente' => [
                        'estado' => $estado->value,
                        'label' => $estado->label(),
                        'desde' => $expediente?->estado_desde->toIso8601String(),
                        'turno' => $estado->turno(),
                        'vence_en' => $expediente?->vence_en?->toDateString(),
                    ],
                    'perfil' => $expediente?->perfil ?? [],
                    'canal_inicio' => $canales->get($cliente->id)?->value,
                    'excepciones' => $excepciones[$cliente->id] ?? ['total' => 0, 'bloqueantes' => 0],
                    'documentos' => $documentos[$cliente->id] ?? null,
                    'created_at' => $cliente->created_at,
                ];
            });

        $filtro = EstadoExpediente::tryFrom((string) $request->query('estado'));

        return Inertia::render('clientes/index', [
            'clientes' => $clientes,
            'estados' => array_map(
                fn (EstadoExpediente $e) => ['valor' => $e->value, 'label' => $e->label()],
                EstadoExpediente::cases(),
            ),
            'perfiles' => array_map(
                fn (PerfilExpediente $p) => ['valor' => $p->value, 'label' => $p->label()],
                PerfilExpediente::cases(),
            ),
            'taxYearActual' => $taxYear,
            'filtroEstado' => $filtro?->value,
        ]);
    }

    /**
     * Excepciones abiertas por cliente (total y bloqueantes), en una consulta.
     *
     * @param  list<int>  $clienteIds
     * @return array<int, array{total: int, bloqueantes: int}>
     */
    private function excepcionesPorCliente(array $clienteIds, int $taxYear): array
    {
        $filas = Excepcion::query()
            ->whereIn('excepciones.estado', [EstadoExcepcion::Abierta, EstadoExcepcion::Consultada])
            ->join('entrevistas', 'entrevistas.id', '=', 'excepciones.entrevista_id')
            ->whereIn('entrevistas.user_id', $clienteIds)
            ->where('entrevistas.tax_year', $taxYear)
            ->selectRaw('entrevistas.user_id as user_id, excepciones.severidad as severidad, count(*) as cuantas')
            ->groupBy('entrevistas.user_id', 'excepciones.severidad')
            ->toBase()
            ->get();

        $resumen = [];

        foreach ($filas as $fila) {
            $resumen[$fila->user_id] ??= ['total' => 0, 'bloqueantes' => 0];
            $resumen[$fila->user_id]['total'] += (int) $fila->cuantas;

            if ($fila->severidad === SeveridadRegla::Bloqueante->value) {
                $resumen[$fila->user_id]['bloqueantes'] += (int) $fila->cuantas;
            }
        }

        return $resumen;
    }

    public function store(ClienteStoreRequest $request): RedirectResponse
    {
        $actor = $request->user();

        $cliente = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Hash::make(Str::random(40)),
            'role' => UserRole::Client,
            'preparer_id' => $actor->role === UserRole::Preparer ? $actor->id : $request->validated('preparer_id'),
        ]);

        // Misma contraseña aleatoria e inutilizable que AgenteToolService::crearCliente()
        // — sin este aviso, este cliente tampoco podría entrar al portal seguro.
        $cliente->notify(new BienvenidaClientePortal(Password::createToken($cliente)));

        return to_route('clientes.show', $cliente);
    }

    /**
     * Eliminar un cliente NUNCA borra su historial de WhatsApp por default —
     * es la fuente de verdad de la conversación, y un cliente real puede
     * necesitar volver a tenerla visible aunque se corrija/recree su
     * cuenta. `eliminar_conversacion_whatsapp` es una opción explícita,
     * pensada para números de prueba: sin ella, el mismo teléfono reutilizado
     * por un cliente nuevo hereda los mensajes huérfanos del anterior (bug
     * real encontrado en producción — el agente llegó a mencionarle a un
     * cliente nuevo el nombre de un dependiente del cliente ya borrado).
     */
    /**
     * Archiva al cliente: deja de verse y no puede entrar, pero sus datos,
     * documentos y conversación se conservan (F0.3). Un usuario de prueba se
     * elimina definitivamente desde UsuarioController::forceDestroy().
     */
    public function destroy(User $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        $this->retencion->archivar($cliente);

        return to_route('clientes.index');
    }

    public function show(Request $request, User $cliente): Response
    {
        $this->authorize('view', $cliente);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        return Inertia::render('ficha/resumen', [
            'ficha' => $this->ficha->encabezado($cliente, $taxYear, $request->user()),
            'resumen' => Inertia::defer(fn () => $this->resumen($cliente, $taxYear), rescue: true),
        ]);
    }

    /**
     * Lo de la pestaña Resumen: diagnóstico, entrevista, consentimiento, los 30
     * eventos más nuevos y la acción principal según el estado. Nada sensible.
     *
     * @return array<string, mixed>
     */
    private function resumen(User $cliente, int $taxYear): array
    {
        $diagnostico = $this->diagnostico->para($cliente, $taxYear);
        $expediente = Expediente::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();
        $entrevista = Entrevista::query()->where('user_id', $cliente->id)->where('tax_year', $taxYear)->first();
        $consentimiento = Consentimiento::query()->with('texto')
            ->where('user_id', $cliente->id)->where('tax_year', $taxYear)->latest('aceptado_en')->first();

        $eventos = $expediente === null ? collect() : ExpedienteEvento::query()
            ->where('expediente_id', $expediente->id)
            ->with('actor:id,name')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return [
            'diagnostico' => $diagnostico->toArray(),
            'entrevista' => $entrevista === null ? null : [
                'estado' => $entrevista->estado->value,
                'iniciada_en' => $entrevista->iniciada_en->toIso8601String(),
                'confirmada_en' => $entrevista->confirmada_en?->toIso8601String(),
                'canal_inicio' => $entrevista->canal_inicio->value,
            ],
            'consentimiento' => $consentimiento === null ? null : [
                'version' => $consentimiento->texto->version,
                'provisional' => $consentimiento->texto->provisional,
                'aceptado_en' => $consentimiento->aceptado_en->toIso8601String(),
            ],
            'eventos' => $eventos->map(fn (ExpedienteEvento $e) => [
                'id' => $e->id,
                'tipo' => $e->tipo->value,
                'tipo_label' => $e->tipo->label(),
                'estado_anterior' => $e->estado_anterior?->value,
                'estado_nuevo' => $e->estado_nuevo?->value,
                'estado_nuevo_label' => $e->estado_nuevo?->label(),
                'actor' => $e->actor?->name,
                'motivo' => $e->motivo,
                'creado_en' => $e->created_at->toIso8601String(),
            ])->values()->all(),
            'accion_principal' => $this->accionPrincipal($cliente, $taxYear, $diagnostico->estado),
        ];
    }

    /**
     * @return array{clave: string, url: string}|null
     */
    private function accionPrincipal(User $cliente, int $taxYear, EstadoExpediente $estado): ?array
    {
        $clave = match ($estado) {
            EstadoExpediente::PorValidar => 'documentos',
            EstadoExpediente::ListoParaPreparar => 'revision',
            EstadoExpediente::EnRevision => Route::has('clientes.hoja-taxwise.show') ? 'hoja' : 'revision',
            default => null,
        };

        $ruta = $clave === null ? null : FichaCliente::PESTANAS[$clave] ?? null;

        if ($ruta === null || ! Route::has($ruta)) {
            return null;
        }

        return ['clave' => $clave, 'url' => route($ruta, ['cliente' => $cliente->id, 'tax_year' => $taxYear])];
    }

    /**
     * Historial de la conversación guardado en `whatsapp_mensajes` (el agente
     * y cualquier mensaje enviado manualmente por un preparador). Ver
     * ESCALAMIENTO A HUMANO en docs/implementar_agente_n8n.md.
     */
    public function conversacionWhatsapp(User $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);

        if (! $cliente->phone) {
            return response()->json(['mensajes' => [], 'control' => null]);
        }

        return response()->json([
            'mensajes' => $this->mensajesLocales($cliente->phone),
            'control' => $this->controlEnvelope($cliente->phone),
        ]);
    }

    public function tomarControlWhatsapp(User $cliente): JsonResponse
    {
        $this->authorize('update', $cliente);

        abort_unless($cliente->phone !== null, 422, 'El cliente no tiene teléfono registrado.');

        $control = WhatsappControl::query()->firstOrCreate(
            ['telefono' => $cliente->phone],
            ['cliente_id' => $cliente->id],
        );
        $control->tomar(request()->user());

        return response()->json(['control' => $this->controlEnvelope($cliente->phone)]);
    }

    public function devolverControlWhatsapp(User $cliente): JsonResponse
    {
        $this->authorize('update', $cliente);

        WhatsappControl::query()->where('telefono', $cliente->phone)->first()?->devolver();

        return response()->json(['control' => $this->controlEnvelope($cliente->phone)]);
    }

    /**
     * Envío manual de un preparador durante el modo `humano` — el mensaje
     * enviado se persiste con rol=preparador para que el agente, al retomar,
     * lo vea con su propio rol en el historial (nunca lo confunda con algo
     * que él mismo dijo — ver ESCALAMIENTO A HUMANO).
     */
    public function enviarMensajeWhatsapp(Request $request, User $cliente): JsonResponse
    {
        $this->authorize('update', $cliente);

        abort_unless($cliente->phone !== null, 422, 'El cliente no tiene teléfono registrado.');

        $request->validate(['mensaje' => ['required', 'string', 'max:1600']]);

        $control = WhatsappControl::query()->where('telefono', $cliente->phone)->first();

        abort_unless($control?->esHumano(), 422, 'Solo se puede enviar un mensaje manual mientras la conversación está en modo humano.');

        $mensaje = (string) $request->string('mensaje');
        $idExterno = $this->canal->enviarTexto($cliente->phone, $mensaje);

        $enviado = WhatsappMensaje::query()->create([
            'telefono' => $cliente->phone,
            'cliente_id' => $cliente->id,
            'rol' => RolMensajeWhatsapp::Preparador,
            'contenido' => $mensaje,
            'mensaje_externo_id' => $idExterno,
            'proveedor' => 'twilio',
        ]);

        return response()->json([
            'mensaje' => ['role' => 'preparador', 'content' => $enviado->contenido, 'created_at' => $enviado->created_at?->toISOString()],
        ]);
    }

    /**
     * @return array<int, array{role: string, content: string, created_at: ?string}>
     */
    private function mensajesLocales(string $telefono): array
    {
        return WhatsappMensaje::query()
            ->where('telefono', $telefono)
            ->orderBy('id')
            ->get()
            ->map(fn (WhatsappMensaje $m) => [
                'role' => match ($m->rol) {
                    RolMensajeWhatsapp::Cliente => 'human',
                    RolMensajeWhatsapp::Agente => 'ai',
                    RolMensajeWhatsapp::Preparador => 'preparador',
                    RolMensajeWhatsapp::Sistema => 'system',
                },
                'content' => $m->contenido,
                'created_at' => $m->created_at?->toISOString(),
            ])
            ->all();
    }

    /**
     * @return array{estado: string, tomado_por: ?string}
     */
    private function controlEnvelope(string $telefono): array
    {
        $control = WhatsappControl::query()->where('telefono', $telefono)->with('tomadoPor')->first();

        return [
            'estado' => $control?->estado->value ?? EstadoControlConversacion::Agente->value,
            'tomado_por' => $control?->tomadoPor?->name,
        ];
    }
}
