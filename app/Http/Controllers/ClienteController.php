<?php

namespace App\Http\Controllers;

use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Http\Concerns\ManagesClientes;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\ClienteExportService;
use Illuminate\Http\RedirectResponse;
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

    public function show(User $cliente): Response
    {
        $this->authorize('view', $cliente);

        $cliente->load([
            'formasCliente',
            'camposCliente' => fn ($query) => $query->with('documento')->orderBy('campo'),
        ]);

        return Inertia::render('clientes/show', [
            'cliente' => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
            ],
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
