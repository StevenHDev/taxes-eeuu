<?php

namespace App\DataTransferObjects;

use App\Enums\FieldMode;
use App\Http\Requests\EventoRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

/**
 * Datos de un evento de recolección ya validados, desacoplados de un
 * FormRequest — permite que EventoRecoleccionService::procesar() se invoque
 * tanto desde el controlador HTTP (agente externo actual, vía fromRequest())
 * como directo desde ToolExecutor (agente de WhatsApp en el mismo proceso,
 * sin request HTTP ni token Sanctum). Ver docs/implementar_agente_n8n.md,
 * decisión de arquitectura sobre EventoRequest.
 */
final readonly class EventoRecoleccionData
{
    /**
     * @param  array<string, mixed>  $datos  Mismo shape que EventoRequest::validated().
     */
    public function __construct(
        public array $datos,
        public ?UploadedFile $file,
        public User $actor,
    ) {}

    public static function fromRequest(EventoRequest $request): self
    {
        return new self(
            datos: $request->validated(),
            file: $request->validated('modo') === FieldMode::Archivo->value ? $request->file('file') : null,
            actor: $request->user(),
        );
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->datos, $key, $default);
    }
}
