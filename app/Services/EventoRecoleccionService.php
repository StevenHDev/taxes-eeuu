<?php

namespace App\Services;

use App\Enums\EventSource;
use App\Enums\FieldDataType;
use App\Enums\FieldMode;
use App\Enums\FieldState;
use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Enums\UserRole;
use App\Http\Requests\EventoRequest;
use App\Models\CampoCliente;
use App\Models\ClientIntakeSession;
use App\Models\Documento;
use App\Models\FormaCliente;
use App\Models\HistorialCambio;
use App\Models\User;
use App\Support\TaxFieldCatalog;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EventoRecoleccionService
{
    /**
     * Procesa un evento de recolección de un solo campo emitido por el agente
     * conversacional (sección 3-4 de la especificación).
     *
     * @return array{cliente: User, campo_cliente: CampoCliente, forma_cliente: FormaCliente}
     */
    public function procesar(EventoRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $cliente = $this->resolverCliente($request);

            $file = $request->validated('modo') === FieldMode::Archivo->value ? $request->file('file') : null;

            return $this->aplicarCambio(
                cliente: $cliente,
                forma: (string) $request->validated('forma'),
                campo: (string) $request->validated('campo'),
                tipoCampo: (string) $request->validated('tipo_campo'),
                modo: FieldMode::from((string) $request->validated('modo')),
                tipoDato: $request->validated('tipo_dato') ? FieldDataType::from((string) $request->validated('tipo_dato')) : null,
                contenido: $request->validated('contenido'),
                file: $file,
                nombreOriginal: $request->validated('nombre_original'),
                actor: $request->user(),
                source: EventSource::AgenteIa,
            );
        });
    }

    /**
     * Corrección manual de un campo por un preparador o administrador desde el panel
     * (sección 6.1: "el contador debe poder corregir un dato mal capturado por el agente").
     *
     * @return array{cliente: User, campo_cliente: CampoCliente, forma_cliente: FormaCliente}
     */
    public function corregirManualmente(
        User $cliente,
        string $forma,
        string $campo,
        string $tipoCampo,
        FieldMode $modo,
        ?FieldDataType $tipoDato,
        mixed $contenido,
        ?UploadedFile $file,
        ?string $nombreOriginal,
        User $actor,
    ): array {
        return DB::transaction(fn () => $this->aplicarCambio(
            cliente: $cliente,
            forma: $forma,
            campo: $campo,
            tipoCampo: $tipoCampo,
            modo: $modo,
            tipoDato: $tipoDato,
            contenido: $contenido,
            file: $file,
            nombreOriginal: $nombreOriginal,
            actor: $actor,
            source: $actor->role === UserRole::Administrator ? EventSource::Administrador : EventSource::Preparador,
        ));
    }

    /**
     * @return array{cliente: User, campo_cliente: CampoCliente, forma_cliente: FormaCliente}
     */
    private function aplicarCambio(
        User $cliente,
        string $forma,
        string $campo,
        string $tipoCampo,
        FieldMode $modo,
        ?FieldDataType $tipoDato,
        mixed $contenido,
        ?UploadedFile $file,
        ?string $nombreOriginal,
        User $actor,
        EventSource $source,
    ): array {
        $field = TaxFieldCatalog::find($forma, $campo);

        $documento = null;
        $valor = null;

        if ($modo === FieldMode::Archivo) {
            [$documento, $estado] = $this->procesarArchivo($file, $cliente, $forma, $campo, $nombreOriginal, $field['formatos_aceptados'] ?? []);
        } else {
            $valor = $contenido;
            $estado = $this->validarContenido($campo, $tipoDato, $field['subcampos'] ?? null, $valor);
        }

        $anterior = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $forma)
            ->where('campo', $campo)
            ->first();

        /** @var CampoCliente $campoCliente */
        $campoCliente = CampoCliente::query()->updateOrCreate(
            ['user_id' => $cliente->id, 'forma' => $forma, 'campo' => $campo],
            [
                'tipo_campo' => $tipoCampo,
                'modo' => $modo,
                'valor_texto' => $modo === FieldMode::Texto ? $valor : null,
                'documento_id' => $documento?->id,
                'estado' => $estado,
                'source' => $source,
                'actualizado_por' => $actor->id,
            ],
        );

        HistorialCambio::query()->create([
            'user_id' => $cliente->id,
            'forma' => $forma,
            'campo' => $campo,
            'valor_anterior' => $anterior?->valor_texto,
            'valor_nuevo' => $modo === FieldMode::Texto ? $valor : $documento->only(['file_original_name', 'formato']),
            'source' => $source,
            'modificado_por' => $actor->id,
        ]);

        $formaCliente = $this->recalcularCompletitud($cliente, $forma);

        return ['cliente' => $cliente, 'campo_cliente' => $campoCliente, 'forma_cliente' => $formaCliente];
    }

    private function resolverCliente(EventoRequest $request): User
    {
        $clienteId = $request->validated('cliente_id');

        if ($clienteId) {
            return User::query()->where('id', $clienteId)->firstOrFail();
        }

        $externalRef = $request->validated('external_ref');

        if ($externalRef) {
            $session = ClientIntakeSession::query()->where('external_ref', $externalRef)->first();

            if ($session) {
                return $session->user;
            }
        }

        $cliente = User::query()->create([
            'name' => 'Cliente sin nombre',
            'email' => sprintf('cliente-%s@pending.local', Str::uuid()),
            'password' => Hash::make(Str::random(40)),
            'role' => UserRole::Client,
        ]);

        if ($externalRef) {
            ClientIntakeSession::query()->create([
                'external_ref' => $externalRef,
                'user_id' => $cliente->id,
            ]);
        }

        return $cliente;
    }

    /**
     * @param  array<int, string>  $formatosAceptados
     * @return array{0: Documento, 1: FieldState}
     */
    private function procesarArchivo(UploadedFile $file, User $cliente, string $forma, string $campo, ?string $nombreOriginal, array $formatosAceptados): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $legible = $file->isValid() && $file->getSize() > 0;
        $formatoValido = ! $formatosAceptados || \in_array($extension, $formatosAceptados, true);
        $estado = $legible && $formatoValido ? FieldState::Recibido : FieldState::Invalido;

        $path = $file->storeAs(
            "documentos/{$cliente->id}",
            Str::uuid().'.'.$extension,
            'local',
        );

        throw_if($path === false, new \RuntimeException('Unable to store the uploaded file.'));

        $size = $file->getSize();

        $documento = Documento::query()->create([
            'user_id' => $cliente->id,
            'forma' => $forma,
            'campo' => $campo,
            'file_path' => $path,
            'file_original_name' => $nombreOriginal ?? $file->getClientOriginalName(),
            'file_mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $size === false ? 0 : $size,
            'formato' => $extension,
            'estado_validacion' => $estado,
        ]);

        return [$documento, $estado];
    }

    /**
     * @param  array<int, string>|null  $subcampos
     */
    private function validarContenido(string $campo, ?FieldDataType $tipoDato, ?array $subcampos, mixed $valor): FieldState
    {
        $valido = match ($tipoDato) {
            FieldDataType::String => $this->validarString($campo, $valor),
            FieldDataType::Number => is_numeric($valor) && (float) $valor >= 0,
            FieldDataType::Object => is_array($valor) && $this->objetoTieneSubcampos($valor, $subcampos),
            FieldDataType::ArrayString => is_array($valor) && collect($valor)->every(fn ($item) => is_string($item)),
            FieldDataType::ArrayObject => is_array($valor) && collect($valor)->every(
                fn ($item) => is_array($item) && $this->objetoTieneSubcampos($item, $subcampos),
            ),
            null => false,
        };

        return $valido ? FieldState::Recibido : FieldState::Invalido;
    }

    private function validarString(string $campo, mixed $valor): bool
    {
        if (! is_string($valor) || $valor === '') {
            return false;
        }

        if ($campo === 'identificacion_ssn_itin') {
            return (bool) preg_match('/^\d{3}-?\d{2}-?\d{4}$/', $valor);
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $valor
     * @param  array<int, string>|null  $subcampos
     */
    private function objetoTieneSubcampos(array $valor, ?array $subcampos): bool
    {
        foreach ($subcampos ?? [] as $subcampo) {
            if (! array_key_exists($subcampo, $valor)) {
                return false;
            }
        }

        if (array_key_exists('ssn', $valor) && filled($valor['ssn']) && ! preg_match('/^\d{3}-?\d{2}-?\d{4}$/', (string) $valor['ssn'])) {
            return false;
        }

        if (array_key_exists('fecha_nacimiento', $valor) && filled($valor['fecha_nacimiento'])) {
            try {
                Carbon::parse($valor['fecha_nacimiento'])->startOfDay();
            } catch (\Throwable) {
                return false;
            }
        }

        return true;
    }

    private function recalcularCompletitud(User $cliente, string $forma): FormaCliente
    {
        $taxForm = TaxForm::from($forma);
        $requeridos = collect(TaxFieldCatalog::requiredFieldsFor($taxForm))->pluck('campo');

        $recibidos = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $forma)
            ->where('estado', FieldState::Recibido)
            ->pluck('campo');

        $completo = $requeridos->diff($recibidos)->isEmpty();

        return FormaCliente::query()->updateOrCreate(
            ['user_id' => $cliente->id, 'forma' => $forma],
            ['estado' => $completo ? FormState::Completo : FormState::EnProgreso],
        );
    }
}
