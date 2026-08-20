<?php

namespace App\Http\Controllers;

use App\Enums\NivelRiesgo;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Http\Requests\ClienteStoreRequest;
use App\Models\CampoCatalogo;
use App\Models\DeterminacionFiscal;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\NivelRiesgoManual;
use App\Models\User;
use App\Services\ClienteExportService;
use App\Services\DocumentoDuplicadoService;
use App\Services\RiesgoCasoService;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
}
