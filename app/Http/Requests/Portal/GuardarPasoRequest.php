<?php

namespace App\Http\Requests\Portal;

use App\Enums\Entrevista\CanalRespuesta;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Services\Entrevista\PasosEntrevista;
use App\Services\Entrevista\RespuestaGuardador;
use App\Services\Entrevista\RespuestaRechazada;
use App\Services\Entrevista\ValorRespuesta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * Guardar un paso de la entrevista (F2.5): `respuestas` es un mapa
 * código → valor con todas las respuestas del paso. Solo acepta códigos de
 * ese paso y valores que calzan con su pregunta (ValorRespuesta); las
 * casillas que no vienen se guardan como "no".
 */
class GuardarPasoRequest extends FormRequest
{
    /** @var Collection<int, array<string, mixed>>|null */
    private ?Collection $preguntas = null;

    private ?Entrevista $entrevista = null;

    public function authorize(): bool
    {
        return $this->user()?->usaEntrevistaCatalogo() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['respuestas' => ['present', 'array']];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $preguntas = $this->preguntasDelPaso()->keyBy('codigo');

            foreach ($this->input('respuestas', []) as $codigo => $valor) {
                $pregunta = $preguntas->get((string) $codigo);

                if ($pregunta === null) {
                    $validator->errors()->add('respuestas', __('La pregunta :codigo no es de este paso.', ['codigo' => $codigo]));

                    continue;
                }

                if ($this->vacio($valor)) {
                    continue;
                }

                try {
                    ValorRespuesta::desde($pregunta, $valor);
                } catch (RespuestaRechazada $e) {
                    $validator->errors()->add("respuestas.{$codigo}", $e->getMessage());
                }
            }
        }];
    }

    public function entrevista(): Entrevista
    {
        return $this->entrevista ??= app(RespuestaGuardador::class)->entrevistaDe($this->user(), CanalRespuesta::Portal);
    }

    /**
     * La persona del paso (solo en la pantalla 4), siempre de la entrevista
     * del propio cliente.
     */
    public function persona(): ?EntrevistaPersona
    {
        $persona = $this->route('persona');

        if ($persona === null) {
            return null;
        }

        return $persona instanceof EntrevistaPersona
            ? $persona
            : EntrevistaPersona::query()->where('entrevista_id', $this->entrevista()->id)->findOrFail($persona);
    }

    public function pantalla(): int
    {
        return $this->persona() !== null ? PasosEntrevista::PANTALLA_PERSONAS : (int) $this->route('pantalla');
    }

    public function paso(): string
    {
        return (string) $this->route('paso');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function preguntasDelPaso(): Collection
    {
        if ($this->preguntas === null) {
            $pasos = app(PasosEntrevista::class);
            $entrevista = $this->entrevista();

            $this->preguntas = $pasos->preguntas($entrevista, $pasos->situacion($entrevista), $this->pantalla(), $this->paso(), $this->persona()?->id);
        }

        return $this->preguntas;
    }

    /**
     * Las respuestas a guardar, en el orden del catálogo: las casillas sin
     * marcar pasan a "no" y lo que vino vacío no se guarda.
     *
     * @return array<string, mixed>
     */
    public function respuestasAGuardar(): array
    {
        $pasos = app(PasosEntrevista::class);
        $enviadas = $this->validated('respuestas');
        $guardar = [];

        foreach ($this->preguntasDelPaso() as $pregunta) {
            $valor = $enviadas[$pregunta['codigo']] ?? null;

            if ($this->vacio($valor)) {
                if (! $pasos->esCasilla($pregunta, $this->pantalla(), $this->paso())) {
                    continue;
                }

                $valor = 'no';
            }

            $guardar[$pregunta['codigo']] = $valor;
        }

        return $guardar;
    }

    private function vacio(mixed $valor): bool
    {
        return $valor === null || $valor === '' || $valor === [];
    }
}
