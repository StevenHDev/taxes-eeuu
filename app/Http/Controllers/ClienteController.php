<?php

namespace App\Http\Controllers;

use App\Enums\EstadoControlConversacion;
use App\Enums\NivelRiesgo;
use App\Enums\RolMensajeWhatsapp;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Http\Requests\ClienteStoreRequest;
use App\Models\CampoCatalogo;
use App\Models\CampoDerivationLog;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\User;
use App\Models\WhatsappControl;
use App\Models\WhatsappMensaje;
use App\Notifications\BienvenidaClientePortal;
use App\Services\ClienteExportService;
use App\Services\DocumentoDuplicadoService;
use App\Services\RiesgoCasoService;
use App\Services\SupabaseWhatsappConversationService;
use App\Services\Whatsapp\WhatsappChannel;
use App\Support\TaxFieldCatalog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClienteController extends Controller
{
    use ManagesClientes;

    public function __construct(
        private readonly ClienteExportService $export,
        private readonly DocumentoDuplicadoService $duplicados,
        private readonly RiesgoCasoService $riesgo,
        private readonly SupabaseWhatsappConversationService $whatsapp,
        private readonly WhatsappChannel $canal,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        // Colección completa (scopeada por rol): el filtrado, orden y paginado
        // se hacen client-side con el DataTable de TanStack en el navegador.
        $clientes = $this->clientesVisiblesPara($request->user())
            ->with(['formasCliente' => fn ($query) => $query->where('tax_year', $taxYear)])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (User $cliente) use ($taxYear) {
                $riesgo = $this->riesgo->nivelEfectivo($cliente, $taxYear);

                return [
                    'id' => $cliente->id,
                    'name' => $cliente->name,
                    'email' => $cliente->email,
                    'phone' => $cliente->phone,
                    'estado_general' => $this->estadoGeneralDe($cliente),
                    'formas' => $cliente->formasCliente->map(fn (FormaCliente $f) => [
                        'forma' => $f->forma,
                        'forma_label' => TaxForm::from($f->forma)->label(),
                        'estado' => $f->estado,
                    ]),
                    'nivel_riesgo' => $riesgo['nivel']->value,
                    'nivel_riesgo_label' => $riesgo['nivel']->label(),
                    'nivel_riesgo_fuente' => $riesgo['fuente'],
                    'created_at' => $cliente->created_at,
                ];
            });

        return Inertia::render('clientes/index', [
            'clientes' => $clientes,
            'formas' => array_map(
                fn (TaxForm $f) => ['value' => $f->value, 'label' => $f->label()],
                TaxForm::cases(),
            ),
            'taxYearActual' => $taxYear,
        ]);
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
    public function destroy(Request $request, User $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        if ($request->boolean('eliminar_conversacion_whatsapp') && $cliente->phone) {
            WhatsappMensaje::query()->where('telefono', $cliente->phone)->delete();
            WhatsappControl::query()->where('telefono', $cliente->phone)->delete();
        }

        $this->eliminarArchivosDe($cliente);
        $cliente->delete();

        return to_route('clientes.index');
    }

    public function show(Request $request, User $cliente): Response
    {
        $this->authorize('view', $cliente);

        // Superficie humana (preparador navegando el panel): default al año
        // fiscal actual cuando no se especifica, con opción de cambiarlo.
        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        $cliente->load([
            'formasCliente' => fn ($query) => $query->where('tax_year', $taxYear),
            'camposCliente' => fn ($query) => $query->where('tax_year', $taxYear)->with('documento')->orderBy('campo'),
            'determinacionesFiscales' => fn ($query) => $query->where('tax_year', $taxYear),
        ]);

        $camposCargados = $cliente->camposCliente->map(fn ($c) => "{$c->forma}:{$c->campo}");
        $unicosCargados = $cliente->camposCliente
            ->filter(fn ($c) => TaxFieldCatalog::isUnicoPorCliente($taxYear, $c->campo))
            ->pluck('campo');

        // Por cada forma real, sus campos propios + transversales-por-forma que el
        // cliente aún no tiene cargados — excluyendo los únicos por cliente, que se
        // agregan una sola vez aparte (no por forma).
        $disponiblePorForma = collect(TaxForm::cases())
            ->flatMap(fn (TaxForm $forma) => collect(TaxFieldCatalog::fieldsFor($taxYear, $forma))
                ->reject(fn (array $campo) => TaxFieldCatalog::isUnicoPorCliente($taxYear, $campo['campo'])
                    || $camposCargados->contains("{$forma->value}:{$campo['campo']}"))
                ->map(fn (array $campo) => [
                    'forma' => $forma->value,
                    'campo' => $campo['campo'],
                    'tipo_campo' => $campo['tipo']->value,
                    'tipo_dato' => $campo['tipo_dato']?->value,
                    'subcampos' => $campo['subcampos'] ?? null,
                    'formatos_aceptados' => $campo['formatos_aceptados'] ?? null,
                    'obligatorio' => $campo['obligatorio'],
                ]));

        // Los campos únicos por cliente (SSN, cónyuge, dependientes, documentos
        // extra...): una sola vez, bajo su propia pseudo-forma ('transversal' o
        // 'documentos_extra', ver CampoCatalogo::pseudoFormas), si no están ya
        // cargados.
        $disponibleUnicos = CampoCatalogo::query()
            ->where('unico_por_cliente', true)
            ->where('tax_year', $taxYear)
            ->orderBy('clave')
            ->get()
            ->reject(fn (CampoCatalogo $c) => $unicosCargados->contains($c->clave))
            ->map(fn (CampoCatalogo $c) => [
                'forma' => $c->forma,
                'campo' => $c->clave,
                'tipo_campo' => $c->tipo_campo->value,
                'tipo_dato' => $c->tipo_dato?->value,
                'subcampos' => $c->subcampos,
                'formatos_aceptados' => $c->formatos_aceptados,
                'obligatorio' => $c->obligatorio,
            ]);

        return Inertia::render('clientes/show', [
            'cliente' => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
            ],
            'taxYearActual' => $taxYear,
            'nivelRiesgo' => $this->riesgo->nivelEfectivo($cliente, $taxYear),
            'catalogoDisponible' => $disponiblePorForma
                ->concat($disponibleUnicos)
                ->values(),
            'formas' => $cliente->formasCliente->map(fn (FormaCliente $f) => [
                'forma' => $f->forma,
                'forma_label' => TaxForm::from($f->forma)->label(),
                'estado' => $f->estado,
                'revisado_en' => $f->revisado_en,
            ]),
            'campos' => $cliente->camposCliente->map(function ($c) use ($taxYear, $request) {
                $definicion = TaxFieldCatalog::find($taxYear, $c->forma, $c->campo);

                return [
                    'forma' => $c->forma,
                    'campo' => $c->campo,
                    'tipo_campo' => $c->tipo_campo,
                    'tipo_dato' => $definicion['tipo_dato']?->value,
                    'subcampos' => $definicion['subcampos'] ?? null,
                    'modo' => $c->modo,
                    'estado' => $c->estado,
                    'advertencia' => $c->advertencia,
                    'valor' => $c->valor,
                    'es_sensible' => $c->esSensible(),
                    'formatos_aceptados' => $definicion['formatos_aceptados'] ?? null,
                    'obligatorio' => $definicion['obligatorio'] ?? false,
                    'documento' => $c->documento ? [
                        'id' => $c->documento->id,
                        'file_original_name' => $c->documento->file_original_name,
                        'file_mime_type' => $c->documento->file_mime_type,
                        'formato' => $c->documento->formato,
                        'estado_validacion' => $c->documento->estado_validacion,
                        'download_url' => $c->documento->downloadUrl(),
                        'preview_url' => $c->documento->previewUrl(),
                        'duplicado' => $this->duplicadoDe($c->documento, $request->user()),
                    ] : null,
                    'updated_at' => $c->updated_at,
                ];
            }),
            'determinaciones' => $cliente->determinacionesFiscales->map(fn (DeterminacionFiscal $d) => [
                'tipo' => $d->tipo,
                'resultado' => $d->resultado,
                'version_reglas' => $d->version_reglas,
                'calculado_en' => $d->calculado_en,
            ]),
            // Traza de qué relaciones documento→campo se cubrieron (o no) al
            // guardar cada documento — ver CampoDerivationLog y
            // EventoRecoleccionService::registrarDerivacion(). Solo interesa
            // al preparador para depurar, así que va aparte de `campos`.
            'derivationLogs' => CampoDerivationLog::query()
                ->where('user_id', $cliente->id)
                ->where('tax_year', $taxYear)
                ->with('documento:id,file_original_name')
                ->latest('id')
                ->get()
                ->map(fn (CampoDerivationLog $log) => [
                    'documento_campo' => $log->documento_campo,
                    'documento_nombre' => $log->documento?->file_original_name,
                    'relaciones_esperadas' => $log->relaciones_esperadas,
                    'relaciones_faltantes' => $log->relaciones_faltantes,
                    'created_at' => $log->created_at,
                ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function duplicadoDe(Documento $documento, User $actor): array
    {
        $coincidencias = $this->duplicados->buscarCoincidencias($documento);

        $mismoCliente = $coincidencias
            ->filter(fn (Documento $d) => $d->user_id === $documento->user_id)
            ->map(fn (Documento $d) => ['forma' => $d->forma, 'campo' => $d->campo])
            ->values();

        $deOtroCliente = $coincidencias->first(fn (Documento $d) => $d->user_id !== $documento->user_id);

        // El actor solo ve el nombre/forma/campo del otro cliente si tiene
        // acceso a ese cliente (mismo límite de visibilidad que separa a los
        // preparadores entre sí) — de lo contrario, solo la señal booleana.
        $otroClienteDetalle = $deOtroCliente && $actor->can('view', $deOtroCliente->user)
            ? ['cliente_id' => $deOtroCliente->user_id, 'cliente_nombre' => $deOtroCliente->user->name, 'forma' => $deOtroCliente->forma, 'campo' => $deOtroCliente->campo]
            : null;

        return [
            'posible_duplicado' => $coincidencias->isNotEmpty(),
            'mismo_cliente' => $mismoCliente->isNotEmpty() ? $mismoCliente->all() : null,
            'otro_cliente' => $deOtroCliente !== null,
            'otro_cliente_detalle' => $otroClienteDetalle,
        ];
    }

    public function marcarRevisado(Request $request, User $cliente, string $forma): RedirectResponse
    {
        $this->authorize('update', $cliente);

        // Acción mutante con consecuencias de auditoría: requerida explícita,
        // sin default de config — el preparador debe declarar qué año revisó.
        $request->validate(['tax_year' => ['required', 'integer', 'digits:4']]);

        $taxForm = TaxForm::from($forma);

        $formaCliente = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $taxForm->value)
            ->where('tax_year', $request->integer('tax_year'))
            ->firstOrFail();

        $formaCliente->marcarRevisado(request()->user());

        return back();
    }

    public function establecerNivelRiesgo(Request $request, User $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        // Acción mutante con consecuencias de auditoría: requerida explícita,
        // sin default de config — mismo criterio que marcarRevisado.
        $request->validate([
            'tax_year' => ['required', 'integer', 'digits:4'],
            'nivel' => ['required', Rule::enum(NivelRiesgo::class)],
        ]);

        NivelRiesgoManual::query()->updateOrCreate(
            ['user_id' => $cliente->id, 'tax_year' => $request->integer('tax_year')],
            [
                'nivel' => $request->enum('nivel', NivelRiesgo::class),
                'establecido_por' => $request->user()->id,
                'establecido_en' => now(),
            ],
        );

        return back();
    }

    public function limpiarNivelRiesgo(Request $request, User $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $request->validate(['tax_year' => ['required', 'integer', 'digits:4']]);

        NivelRiesgoManual::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $request->integer('tax_year'))
            ->delete();

        return back();
    }

    public function export(Request $request, User $cliente): BinaryFileResponse
    {
        $this->authorize('view', $cliente);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        $zipPath = $this->export->exportarZip($cliente, $taxYear);

        return response()->download($zipPath, "cliente-{$cliente->id}-{$taxYear}.zip")->deleteFileAfterSend();
    }

    /**
     * Historial completo de la conversación: el de Supabase (n8n, hoy de solo
     * lectura) más lo que ya exista localmente en `whatsapp_mensajes` (el
     * nuevo agente, y cualquier mensaje enviado manualmente por un
     * preparador) — mezclados y ordenados por instante real, no por el
     * string crudo de `created_at` (Supabase devuelve formatos mixtos, ver
     * SupabaseWhatsappConversationService). Ver ESCALAMIENTO A HUMANO en
     * docs/implementar_agente_n8n.md.
     */
    public function conversacionWhatsapp(User $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);

        if (! $cliente->phone) {
            return response()->json(['mensajes' => [], 'control' => null]);
        }

        $mensajes = collect($this->whatsapp->paraTelefono($cliente->phone))
            ->concat($this->mensajesLocales($cliente->phone))
            ->sortBy(fn (array $m) => $m['created_at'] ? Carbon::parse($m['created_at'])->timestamp : 0)
            ->values();

        return response()->json([
            'mensajes' => $mensajes,
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
            'proveedor' => config('services.whatsapp.provider'),
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
