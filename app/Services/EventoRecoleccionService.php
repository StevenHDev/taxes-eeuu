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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventoRecoleccionService
{
    /**
     * Procesa un evento de recolección de un solo campo emitido por el agente
     * conversacional (sección 3-4 de la especificación).
     *
     * @return array{cliente: User, campo_cliente: CampoCliente, forma_cliente: ?FormaCliente}
     */
    public function procesar(EventoRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            $cliente = $this->resolverCliente($request);

            $file = $request->validated('modo') === FieldMode::Archivo->value ? $request->file('file') : null;

            return $this->aplicarCambio(
                cliente: $cliente,
                taxYear: (int) $request->validated('tax_year'),
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
                acumular: $request->boolean('acumular'),
                subcampoAcumular: $request->validated('subcampo'),
            );
        });
    }

    /**
     * Corrección manual de un campo por un preparador o administrador desde el panel
     * (sección 6.1: "el contador debe poder corregir un dato mal capturado por el agente").
     *
     * @return array{cliente: User, campo_cliente: CampoCliente, forma_cliente: ?FormaCliente}
     */
    public function corregirManualmente(
        User $cliente,
        int $taxYear,
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
        // Una corrección manual siempre reemplaza el valor tal cual lo escribe el
        // preparador — nunca suma sobre lo que hubiera, aunque el campo/subcampo
        // esté marcado como acumulable para el agente (acumular=false, siempre).
        return DB::transaction(fn () => $this->aplicarCambio(
            cliente: $cliente,
            taxYear: $taxYear,
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
     * @return array{cliente: User, campo_cliente: CampoCliente, forma_cliente: ?FormaCliente}
     */
    private function aplicarCambio(
        User $cliente,
        int $taxYear,
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
        bool $acumular = false,
        ?string $subcampoAcumular = null,
    ): array {
        $field = TaxFieldCatalog::find($taxYear, $forma, $campo);

        // Los campos únicos por cliente se guardan bajo la forma canónica
        // 'transversal' — una sola fila compartida por todas las formas — para no
        // duplicar el mismo dato personal (SSN, cónyuge, dependientes) por forma.
        $formaAlmacen = TaxFieldCatalog::formaAlmacen($taxYear, $campo, $forma);

        $anterior = CampoCliente::query()
            ->where('user_id', $cliente->id)
            ->where('forma', $formaAlmacen)
            ->where('campo', $campo)
            ->where('tax_year', $taxYear)
            ->first();

        $documento = null;
        $valor = null;

        if ($modo === FieldMode::Archivo) {
            [$documento, $estado] = $this->procesarArchivo($file, $cliente, $taxYear, $formaAlmacen, $campo, $nombreOriginal, $field['formatos_aceptados'] ?? []);
        } elseif ($modo === FieldMode::NoAplica) {
            // Respuesta explícita del cliente ("no lo tengo"/"no aplica"), no la
            // ausencia de un valor — EventoRequest/CampoClienteUpdateRequest ya
            // garantizan que el campo es opcional antes de llegar acá.
            $estado = FieldState::NoAplica;
        } else {
            $valor = $acumular
                ? $this->acumularValor($contenido, $tipoDato, $subcampoAcumular, $anterior?->valor_texto)
                : $contenido;
            $estado = $this->validarContenido($campo, $tipoDato, $field['subcampos'] ?? null, $valor);
        }

        // Si el campo ya tenía un documento asociado (ej. un archivo inválido
        // reemplazado, o ahora marcado "no aplica"), el anterior queda obsoleto.
        if ($anterior?->documento_id && $anterior->documento_id !== $documento?->id) {
            $this->borrarDocumento($anterior->documento);
        }

        /** @var CampoCliente $campoCliente */
        $campoCliente = CampoCliente::query()->updateOrCreate(
            ['user_id' => $cliente->id, 'forma' => $formaAlmacen, 'campo' => $campo, 'tax_year' => $taxYear],
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
            'forma' => $formaAlmacen,
            'tax_year' => $taxYear,
            'campo' => $campo,
            'valor_anterior' => $anterior?->valor_texto,
            'valor_nuevo' => match ($modo) {
                FieldMode::Texto => $valor,
                FieldMode::Archivo => $documento->only(['file_original_name', 'formato']),
                FieldMode::NoAplica => 'no_aplica',
            },
            'source' => $source,
            'modificado_por' => $actor->id,
        ]);

        $formaCliente = $this->recalcularAfectadas($cliente, $taxYear, $forma, $campo);

        return ['cliente' => $cliente, 'campo_cliente' => $campoCliente, 'forma_cliente' => $formaCliente];
    }

    /**
     * Elimina un campo cargado por error (sección 6.1 del plan: el preparador debe
     * poder corregir o quitar un dato mal capturado). Se conserva `historial_cambios`
     * (con `valor_nuevo: null`) para trazabilidad; lo que se borra es la fila
     * "actual" en `campos_cliente` y, si correspondía, el documento y su archivo.
     */
    public function eliminarCampo(User $cliente, int $taxYear, string $forma, string $campo, User $actor): void
    {
        DB::transaction(function () use ($cliente, $taxYear, $forma, $campo, $actor) {
            $formaAlmacen = TaxFieldCatalog::formaAlmacen($taxYear, $campo, $forma);

            $campoCliente = CampoCliente::query()
                ->where('user_id', $cliente->id)
                ->where('forma', $formaAlmacen)
                ->where('campo', $campo)
                ->where('tax_year', $taxYear)
                ->first();

            if (! $campoCliente) {
                return;
            }

            $source = $actor->role === UserRole::Administrator ? EventSource::Administrador : EventSource::Preparador;

            HistorialCambio::query()->create([
                'user_id' => $cliente->id,
                'forma' => $formaAlmacen,
                'tax_year' => $taxYear,
                'campo' => $campo,
                'valor_anterior' => $campoCliente->valor_texto,
                'valor_nuevo' => null,
                'source' => $source,
                'modificado_por' => $actor->id,
            ]);

            if ($campoCliente->documento_id) {
                $this->borrarDocumento($campoCliente->documento);
            }

            $campoCliente->delete();

            $this->recalcularAfectadas($cliente, $taxYear, $forma, $campo);
        });
    }

    private function borrarDocumento(?Documento $documento): void
    {
        if (! $documento) {
            return;
        }

        Storage::disk('local')->delete($documento->file_path);
        $documento->delete();
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

        $phone = $request->validated('phone');

        // El teléfono es un identificador más estable que external_ref para
        // reconocer al mismo cliente entre eventos: si ya existe un cliente con
        // ese teléfono, se reutiliza en vez de crear uno nuevo.
        if ($phone) {
            $porTelefono = User::query()->where('role', UserRole::Client)->where('phone', $phone)->first();

            if ($porTelefono) {
                return $porTelefono;
            }
        }

        $cliente = User::query()->create([
            'name' => 'Cliente sin nombre',
            'email' => sprintf('cliente-%s@pending.local', Str::uuid()),
            'phone' => $phone,
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
    private function procesarArchivo(UploadedFile $file, User $cliente, int $taxYear, string $forma, string $campo, ?string $nombreOriginal, array $formatosAceptados): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $legible = $file->isValid() && $file->getSize() > 0;
        $formatoValido = ! $formatosAceptados || \in_array($extension, $formatosAceptados, true);
        $estado = $legible && $formatoValido ? FieldState::Recibido : FieldState::Invalido;

        // Se calcula sobre el archivo temporal de subida, antes de moverlo con
        // storeAs() — permite detectar el mismo documento reutilizado entre
        // clientes distintos (ver DocumentoDuplicadoService). Nunca truena si
        // getRealPath() falla: un hash ausente solo desactiva la detección
        // de duplicados para ese documento puntual, no la subida en sí.
        $realPath = $file->getRealPath();
        $hash = $realPath !== false ? hash_file('sha256', $realPath) : null;

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
            'tax_year' => $taxYear,
            'campo' => $campo,
            'file_path' => $path,
            'file_original_name' => $nombreOriginal ?? $file->getClientOriginalName(),
            'file_mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'file_size' => $size === false ? 0 : $size,
            'formato' => $extension,
            'hash_contenido' => $hash === false ? null : $hash,
            'estado_validacion' => $estado,
        ]);

        return [$documento, $estado];
    }

    /**
     * Suma el aporte de este evento sobre lo ya guardado, en vez de
     * sobrescribirlo — para un campo/subcampo que más de un documento puede
     * revelar (ej. `intereses_dividendos` desde un 1099-INT y un 1099-DIV,
     * ver RelacionDocumentoCampo::$acumulable). Solo aplica a `procesar()`
     * (evento del agente); `corregirManualmente()` nunca pasa acumular=true.
     */
    private function acumularValor(mixed $contenido, ?FieldDataType $tipoDato, ?string $subcampo, mixed $valorAnterior): mixed
    {
        if ($tipoDato === FieldDataType::Number) {
            $previo = is_numeric($valorAnterior) ? (float) $valorAnterior : 0.0;

            return $previo + (float) $contenido;
        }

        if ($tipoDato === FieldDataType::Object && is_array($contenido) && $subcampo !== null) {
            $previo = is_array($valorAnterior) && is_numeric($valorAnterior[$subcampo] ?? null)
                ? (float) $valorAnterior[$subcampo]
                : 0.0;

            $contenido[$subcampo] = $previo + (float) ($contenido[$subcampo] ?? 0);

            return $contenido;
        }

        return $contenido;
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

    /**
     * Recalcula la completitud de las formas afectadas por un cambio, para un
     * año fiscal dado. Para un campo normal, solo su forma; para un campo único
     * por cliente, además todas las formas de ese cliente EN ESE MISMO AÑO
     * (porque ese dato cuenta para la completitud de todas las formas del año,
     * pero nunca para las de otro año fiscal).
     */
    private function recalcularAfectadas(User $cliente, int $taxYear, string $forma, string $campo): ?FormaCliente
    {
        $formaReal = TaxForm::tryFrom($forma);

        $formas = collect();

        if ($formaReal) {
            $formas->push($formaReal->value);
        }

        if (TaxFieldCatalog::isUnicoPorCliente($taxYear, $campo)) {
            $formas = $formas
                ->merge(
                    FormaCliente::query()
                        ->where('user_id', $cliente->id)
                        ->where('tax_year', $taxYear)
                        ->pluck('forma'),
                )
                ->unique()
                ->values();
        }

        $resultados = [];

        foreach ($formas as $f) {
            $resultados[$f] = $this->recalcularCompletitud($cliente, $taxYear, $f);
        }

        if ($formaReal && isset($resultados[$formaReal->value])) {
            return $resultados[$formaReal->value];
        }

        return $resultados === [] ? null : reset($resultados);
    }

    private function recalcularCompletitud(User $cliente, int $taxYear, string $forma): FormaCliente
    {
        $taxForm = TaxForm::from($forma);
        $completo = TaxFieldCatalog::pendientesObligatoriosFor($taxYear, $taxForm, $cliente->id)->isEmpty();

        return FormaCliente::query()->updateOrCreate(
            ['user_id' => $cliente->id, 'forma' => $forma, 'tax_year' => $taxYear],
            ['estado' => $completo ? FormState::Completo : FormState::EnProgreso],
        );
    }
}
