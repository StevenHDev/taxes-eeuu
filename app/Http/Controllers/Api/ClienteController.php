<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApiAbility;
use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Http\Concerns\ManagesClientes;
use App\Http\Controllers\Controller;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\ClienteExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $clientes = $this->clientesVisiblesPara($request->user())
            ->with('formasCliente')
            ->paginate(20);

        return response()->json([
            'data' => $clientes->through(fn (User $cliente) => [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'email' => $cliente->email,
                'estado_general' => $this->estadoGeneral($cliente),
            ]),
            'meta' => ['current_page' => $clientes->currentPage(), 'last_page' => $clientes->lastPage()],
        ]);
    }

    public function show(Request $request, User $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $cliente->load(['formasCliente', 'camposCliente.documento']);

        return response()->json([
            'id' => $cliente->id,
            'name' => $cliente->name,
            'email' => $cliente->email,
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
        ]);
    }

    public function documentos(Request $request, User $cliente): JsonResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        return response()->json([
            'data' => $cliente->camposCliente()
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

        $taxForm = TaxForm::from($forma);

        $formaCliente = FormaCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $taxForm->value)
            ->firstOrFail();

        $formaCliente->marcarRevisado($request->user());

        return response()->json(['forma' => $formaCliente->forma, 'estado' => $formaCliente->estado, 'revisado_en' => $formaCliente->revisado_en]);
    }

    public function export(Request $request, User $cliente): BinaryFileResponse
    {
        $this->authorize('view', $cliente);
        $this->ensureAbility($request, ApiAbility::ClientesRead);

        $zipPath = $this->export->exportarZip($cliente);

        return response()->download($zipPath, "cliente-{$cliente->id}.zip")->deleteFileAfterSend();
    }

    private function ensureAbility(Request $request, ApiAbility $ability): void
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken && ! $token->can($ability->value)) {
            abort(403, 'El token no tiene la ability requerida: '.$ability->value);
        }
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
