<?php

namespace App\Http\Controllers;

use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Http\Requests\ClienteStoreRequest;
use App\Models\CampoCatalogo;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\ClienteExportService;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClienteController extends Controller
{
    use ManagesClientes;

    public function __construct(private readonly ClienteExportService $export) {}

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
            ->map(fn (User $cliente) => [
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
                'created_at' => $cliente->created_at,
            ]);

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

        return to_route('clientes.show', $cliente);
    }

    public function destroy(User $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

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
                    'formatos_aceptados' => $campo['formatos_aceptados'] ?? null,
                ]));

        // Los campos únicos por cliente (SSN, cónyuge, dependientes): una sola vez,
        // bajo la forma canónica 'transversal', si no están ya cargados.
        $disponibleUnicos = CampoCatalogo::query()
            ->where('unico_por_cliente', true)
            ->where('tax_year', $taxYear)
            ->orderBy('clave')
            ->get()
            ->reject(fn (CampoCatalogo $c) => $unicosCargados->contains($c->clave))
            ->map(fn (CampoCatalogo $c) => [
                'forma' => CampoCatalogo::TRANSVERSAL,
                'campo' => $c->clave,
                'tipo_campo' => $c->tipo_campo->value,
                'formatos_aceptados' => $c->formatos_aceptados,
            ]);

        return Inertia::render('clientes/show', [
            'cliente' => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
            ],
            'taxYearActual' => $taxYear,
            'catalogoDisponible' => $disponiblePorForma
                ->concat($disponibleUnicos)
                ->values(),
            'formas' => $cliente->formasCliente->map(fn (FormaCliente $f) => [
                'forma' => $f->forma,
                'forma_label' => TaxForm::from($f->forma)->label(),
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
                'es_sensible' => $c->esSensible(),
                'formatos_aceptados' => TaxFieldCatalog::find($taxYear, $c->forma, $c->campo)['formatos_aceptados'] ?? null,
                'documento' => $c->documento ? [
                    'id' => $c->documento->id,
                    'file_original_name' => $c->documento->file_original_name,
                    'file_mime_type' => $c->documento->file_mime_type,
                    'formato' => $c->documento->formato,
                    'estado_validacion' => $c->documento->estado_validacion,
                    'download_url' => $c->documento->downloadUrl(),
                    'preview_url' => $c->documento->previewUrl(),
                ] : null,
                'updated_at' => $c->updated_at,
            ]),
        ]);
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

    public function export(Request $request, User $cliente): BinaryFileResponse
    {
        $this->authorize('view', $cliente);

        $taxYear = (int) $request->query('tax_year', config('tax.current_tax_year'));

        $zipPath = $this->export->exportarZip($cliente, $taxYear);

        return response()->download($zipPath, "cliente-{$cliente->id}-{$taxYear}.zip")->deleteFileAfterSend();
    }
}
