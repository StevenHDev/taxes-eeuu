<?php

namespace App\Http\Controllers;

use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Http\Concerns\ManagesClientes;
use App\Http\Requests\ClienteStoreRequest;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\ClienteExportService;
use App\Support\TaxFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClienteController extends Controller
{
    use ManagesClientes;

    public function __construct(private readonly ClienteExportService $export) {}

    public function index(): Response
    {
        $this->authorize('viewAny', User::class);

        $clientes = $this->clientesVisiblesPara(request()->user())
            ->withCount(['formasCliente'])
            ->with(['formasCliente'])
            ->orderByDesc('created_at')
            ->paginate(20)
            ->through(fn (User $cliente) => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
                'estado_general' => $this->estadoGeneral($cliente),
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

    public function show(User $cliente): Response
    {
        $this->authorize('view', $cliente);

        $cliente->load([
            'formasCliente',
            'camposCliente' => fn ($query) => $query->with('documento')->orderBy('campo'),
        ]);

        $camposCargados = $cliente->camposCliente->map(fn ($c) => "{$c->forma}:{$c->campo}");

        return Inertia::render('clientes/show', [
            'cliente' => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
            ],
            // Por cada forma real, todos sus campos (transversales + propios) que este
            // cliente todavía no tiene cargados — para el diálogo "Agregar campo".
            'catalogoDisponible' => collect(TaxForm::cases())
                ->flatMap(fn (TaxForm $forma) => collect(TaxFieldCatalog::fieldsFor($forma))
                    ->reject(fn (array $campo) => $camposCargados->contains("{$forma->value}:{$campo['campo']}"))
                    ->map(fn (array $campo) => [
                        'forma' => $forma->value,
                        'campo' => $campo['campo'],
                        'tipo_campo' => $campo['tipo']->value,
                    ]))
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
                'documento' => $c->documento ? [
                    'id' => $c->documento->id,
                    'file_original_name' => $c->documento->file_original_name,
                    'formato' => $c->documento->formato,
                    'estado_validacion' => $c->documento->estado_validacion,
                    'download_url' => $c->documento->downloadUrl(),
                ] : null,
                'updated_at' => $c->updated_at,
            ]),
        ]);
    }

    public function marcarRevisado(User $cliente, string $forma): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $taxForm = TaxForm::from($forma);

        $formaCliente = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $taxForm->value)
            ->firstOrFail();

        $formaCliente->marcarRevisado(request()->user());

        return back();
    }

    public function export(User $cliente): BinaryFileResponse
    {
        $this->authorize('view', $cliente);

        $zipPath = $this->export->exportarZip($cliente);

        return response()->download($zipPath, "cliente-{$cliente->id}.zip")->deleteFileAfterSend();
    }

    private function estadoGeneral(User $cliente): string
    {
        if ($cliente->formasCliente->isEmpty()) {
            return 'sin_iniciar';
        }

        if ($cliente->formasCliente->every(fn (FormaCliente $f) => $f->estado === FormState::Completo)) {
            return 'completo';
        }

        return 'en_progreso';
    }
}
