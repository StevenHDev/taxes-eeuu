<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiAbility;
use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClienteFormasRequest;
use App\Http\Requests\ClienteStoreRequest;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\ClienteExportService;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClienteController extends Controller
{
    use ManagesClientes;

    public function __construct(private readonly ClienteExportService $export) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $search = $request->string('search')->toString() ?: null;
        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        $clientes = $this->clientesVisiblesPara($request->user(), $search)
            ->with(['formasCliente' => fn ($query) => $query->where('tax_year', $taxYear)])
            ->paginate(20);

        return response()->json([
            'data' => $clientes->through(fn (User $cliente) => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
                'estado_general' => $this->estadoGeneralDe($cliente),
            ]),
            'meta' => ['current_page' => $clientes->currentPage(), 'last_page' => $clientes->lastPage()],
            'tax_year' => $taxYear,
        ]);
    }

    /**
     * Alta de un cliente desde una integración (mismas reglas que el alta manual
     * de /clientes: rol fijo `client`, email/teléfono únicos). Pensado para crear
     * al cliente con datos reales antes de emitir eventos, en vez de dejar que
     * `/eventos` genere el placeholder "Cliente sin nombre".
     */
    public function store(ClienteStoreRequest $request): JsonResponse
    {
        $this->ensureAbility($request, ApiAbility::ClientesWrite);

        $actor = $request->user();

        $cliente = User::query()->create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'password' => Hash::make(Str::random(40)),
            'role' => UserRole::Client,
            'preparer_id' => $actor->role === UserRole::Preparer ? $actor->id : $request->validated('preparer_id'),
        ]);

        return response()->json($this->detalle($cliente, (int) config('tax.current_tax_year')), 201);
    }

    public function show(Request $request, User $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        return response()->json($this->detalle($cliente, $taxYear));
    }

    /**
     * Busca el detalle de un cliente por `id`, `phone` o `email` — pensado para que
     * el agente conversacional (u otra integración) resuelva el `cliente_id` a partir
     * del teléfono o el correo antes de emitir eventos, en vez de arrastrar `external_ref`.
     */
    public function buscar(Request $request): JsonResponse
    {
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $request->validate([
            'id' => ['required_without_all:phone,email', 'nullable', 'integer'],
            'phone' => ['required_without_all:id,email', 'nullable', 'string'],
            'email' => ['required_without_all:id,phone', 'nullable', 'string', 'email'],
        ]);

        $cliente = $this->clientesVisiblesPara($request->user())
            ->when($request->filled('id'), fn ($q) => $q->where('id', $request->integer('id')))
            ->when($request->filled('phone'), fn ($q) => $q->where('phone', $request->string('phone')))
            ->when($request->filled('email'), fn ($q) => $q->where('email', $request->string('email')))
            ->first();

        if (! $cliente) {
            return response()->json(['message' => 'Cliente no encontrado.'], 404);
        }

        $this->authorize('view', $cliente);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        return response()->json($this->detalle($cliente, $taxYear));
    }

    /**
     * @return array<string, mixed>
     */
    private function detalle(User $cliente, int $taxYear): array
    {
        $cliente->load([
            'formasCliente' => fn ($query) => $query->where('tax_year', $taxYear),
            'camposCliente' => fn ($query) => $query->where('tax_year', $taxYear)->with('documento'),
        ]);

        return [
            'id' => $cliente->id,
            'name' => $cliente->name,
            'email' => $cliente->email,
            'phone' => $cliente->phone,
            'tax_year' => $taxYear,
            'formas' => $cliente->formasCliente->map(fn (FormaCliente $f) => [
                'forma' => $f->forma,
                'estado' => $f->estado,
                'revisado_en' => $f->revisado_en,
            ]),
            'campos' => $cliente->camposCliente->map(fn ($c) => [
                'forma' => $c->forma,
                'campo' => $c->campo,
                'tipo_campo' => $c->tipo_campo,
                'modo' => $c->modo,
                'estado' => $c->estado,
                'valor' => $c->valor,
                'updated_at' => $c->updated_at,
            ]),
        ];
    }

    /**
     * Declara las formas aplicables de un cliente (sección 4 del roadmap: el
     * agente conversacional externo la invoca apenas resuelve, en lenguaje
     * natural, su propio árbol de determinación de forma). Idempotente: usa
     * firstOrCreate para no resetear a en_progreso una forma que ya esté
     * completa si el agente vuelve a declarar formas más adelante en la
     * conversación (ej. el cliente menciona una situación adicional).
     */
    public function formas(ClienteFormasRequest $request, User $cliente): JsonResponse
    {
        $taxYear = (int) $request->validated('tax_year');

        foreach ((array) $request->validated('formas') as $forma) {
            FormaCliente::query()->firstOrCreate(
                ['user_id' => $cliente->id, 'forma' => $forma, 'tax_year' => $taxYear],
                ['estado' => FormState::EnProgreso],
            );
        }

        return response()->json($this->pendientesEnvelope($cliente, $taxYear));
    }

    /**
     * Qué le falta a un cliente por entregar, a través de todas sus formas
     * declaradas — para que el agente conversacional externo sepa qué pedir a
     * continuación sin tener que memorizar el catálogo (sustituye el catálogo
     * hardcodeado que tenía antes en su propio prompt).
     */
    public function pendientes(Request $request, User $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        // Acción de lectura consultada por una integración externa: tax_year
        // explícito siempre, igual que el resto del camino del agente.
        $request->validate(['tax_year' => ['required', 'integer', 'digits:4']]);

        return response()->json($this->pendientesEnvelope($cliente, (int) $request->query('tax_year')));
    }

    /**
     * @return array<string, mixed>
     */
    private function pendientesEnvelope(User $cliente, int $taxYear): array
    {
        $formas = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->pluck('forma')
            ->map(fn (string $forma) => TaxForm::tryFrom($forma))
            ->filter()
            ->values()
            ->all();

        // Los transversales (SSN, cónyuge, dependientes, estado_civil...) no
        // pertenecen a ninguna forma — se piden sin importar cuál(es) apliquen,
        // así que aparecen en `pendientes` incluso si el agente todavía no llamó
        // a /formas (PASO A-D no ha terminado). `completo`, en cambio, sí exige
        // al menos una forma declarada: sin eso la determinación en sí sigue
        // pendiente, aunque ya no falte ningún transversal.
        $pendientes = TaxFieldCatalog::pendientesPara($taxYear, $formas, $cliente->id);

        // `siguiente` es el PRIMER elemento de `pendientes`, en el orden que ya
        // trae el catálogo (transversales primero, documentos y datos
        // opcionales incluidos) — nunca solo el primer obligatorio. El agente
        // externo pregunta uno a uno lo que indique `siguiente`, sin importar
        // si es opcional, para poder ofrecer un documento (ej. w2, 1099-nec)
        // ANTES de pedirle al cliente que teclee a mano un monto que ese mismo
        // documento ya revela (ver RelacionDocumentoCampo/`revela`) — filtrar
        // por obligatorio acá saltaría siempre los documentos opcionales y
        // pediría el dato manual primero, inutilizando esa relación. Si el
        // cliente no tiene el documento, el flujo normal de "no_aplica" ya lo
        // cubre — este cambio no afecta eso.
        $siguiente = $pendientes[0] ?? null;

        // `completo`, en cambio, sigue mirando solo obligatorios: un opcional
        // sin resolver (el cliente nunca llegó a que se lo ofrecieran, o está
        // pendiente de "no_aplica") nunca debe bloquear el cierre de la forma.
        $quedaObligatorioPendiente = collect($pendientes)->contains(fn (array $p) => $p['obligatorio']);

        return [
            'tax_year' => $taxYear,
            'completo' => $formas !== [] && ! $quedaObligatorioPendiente,
            'pendientes' => $pendientes,
            'siguiente' => $siguiente ? ['forma' => $siguiente['forma'], 'campo' => $siguiente['campo']] : null,
        ];
    }

    public function documentos(Request $request, User $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        return response()->json([
            'data' => $cliente->camposCliente()
                ->where('tax_year', $taxYear)
                ->whereNotNull('documento_id')
                ->with('documento')
                ->get()
                ->pluck('documento')
                ->map(fn ($d) => [
                    'id' => $d->id,
                    'forma' => $d->forma,
                    'campo' => $d->campo,
                    'file_original_name' => $d->file_original_name,
                    'formato' => $d->formato,
                    'estado_validacion' => $d->estado_validacion,
                    'download_url' => $d->downloadUrl(),
                    'created_at' => $d->created_at,
                ]),
        ]);
    }

    public function marcarRevisado(Request $request, User $cliente, string $forma): JsonResponse
    {
        $this->authorize('update', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesWrite);

        // Acción mutante con consecuencias de auditoría: requerida explícita,
        // sin default de config.
        $request->validate(['tax_year' => ['required', 'integer', 'digits:4']]);

        $taxForm = TaxForm::from($forma);

        $formaCliente = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $taxForm->value)
            ->where('tax_year', $request->integer('tax_year'))
            ->firstOrFail();

        $formaCliente->marcarRevisado($request->user());

        return response()->json(['forma' => $formaCliente->forma, 'estado' => $formaCliente->estado, 'revisado_en' => $formaCliente->revisado_en]);
    }

    public function export(Request $request, User $cliente): BinaryFileResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        $zipPath = $this->export->exportarZip($cliente, $taxYear);

        return response()->download($zipPath, "cliente-{$cliente->id}-{$taxYear}.zip")->deleteFileAfterSend();
    }

    private function ensureAbility(Request $request, ApiAbility $ability): void
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability->value)) {
            abort(403, 'El token no tiene la ability requerida: '.$ability->value);
        }
    }
}
