<?php

namespace App\Services\Entrevista;

use App\Enums\Entrevista\CanalRespuesta;
use App\Enums\Entrevista\EstadoEntrevista;
use App\Enums\FormState;
use App\Enums\TaxForm;
use App\Models\Entrevista\Entrevista;
use App\Models\Entrevista\EntrevistaPersona;
use App\Models\Entrevista\EntrevistaRespuesta;
use App\Models\FormaCliente;
use App\Models\User;
use App\Services\CatalogoFiscal\CatalogoFiscal;
use Illuminate\Support\Facades\DB;

/**
 * Único camino de escritura de la entrevista (F2.2), para el portal, el
 * agente, el preparador y la migración. Valida contra el catálogo del año
 * y el motor, guarda cifrado, reabre una entrevista confirmada y mantiene
 * `FormaCliente` al día para las pantallas que todavía leen las formas
 * declaradas (lista de clientes, dashboard, cálculo) hasta la fase 5.
 *
 * Un canal `whatsapp` nunca guarda una pregunta sensible: la regla vive
 * aquí, no solo en el prompt.
 */
class RespuestaGuardador
{
    /**
     * Formularios del catálogo que tienen forma declarada equivalente.
     */
    private const FORMAS = [
        '1040' => TaxForm::Form1040,
        'sch-c' => TaxForm::ScheduleC,
        'sch-e' => TaxForm::ScheduleE,
    ];

    public function __construct(
        private readonly CatalogoFiscal $catalogo,
        private readonly MotorEntrevista $motor,
    ) {}

    /**
     * La entrevista del cliente en el año en curso (`tax.current_tax_year`),
     * creada si todavía no existe.
     */
    public function entrevistaDe(User $cliente, CanalRespuesta $canal): Entrevista
    {
        return Entrevista::query()->firstOrCreate(
            ['user_id' => $cliente->id, 'tax_year' => (int) config('tax.current_tax_year')],
            ['estado' => EstadoEntrevista::EnCurso, 'canal_inicio' => $canal, 'iniciada_en' => now()],
        );
    }

    public function agregarPersona(Entrevista $entrevista, ?string $etiqueta = null): EntrevistaPersona
    {
        return DB::transaction(function () use ($entrevista, $etiqueta) {
            // Se bloquea la entrevista y no las personas: PostgreSQL no acepta
            // FOR UPDATE junto con max().
            Entrevista::query()->whereKey($entrevista->id)->lockForUpdate()->first();
            $orden = (int) EntrevistaPersona::withTrashed()->where('entrevista_id', $entrevista->id)->max('orden') + 1;

            $persona = $entrevista->personas()->create(['orden' => $orden, 'etiqueta' => $etiqueta ?? "Persona {$orden}"]);
            $entrevista->unsetRelation('personas');

            return $persona;
        });
    }

    /**
     * Quitar una persona la archiva con sus respuestas (F0.3); los
     * documentos y formularios que activaba se recalculan.
     */
    public function quitarPersona(EntrevistaPersona $persona): void
    {
        DB::transaction(function () use ($persona) {
            $persona->delete();

            $entrevista = $persona->entrevista;
            $entrevista->unsetRelation('personas');
            $this->sincronizarFormas($entrevista, $this->motor->situacion($entrevista));
        });
    }

    /**
     * @throws RespuestaRechazada
     */
    public function guardar(Entrevista $entrevista, RespuestaData $datos, CanalRespuesta $canal, ?User $actor): EntrevistaRespuesta
    {
        return DB::transaction(function () use ($entrevista, $datos, $canal, $actor) {
            Entrevista::query()->whereKey($entrevista->id)->lockForUpdate()->first();
            $entrevista->unsetRelation('respuestas')->unsetRelation('personas');

            $codigo = $datos->preguntaCodigo;
            $pregunta = $this->catalogo->preguntas($entrevista->tax_year)->firstWhere('codigo', $codigo)
                ?? throw new RespuestaRechazada($codigo, __('La pregunta :codigo no existe en :anio.', ['codigo' => $codigo, 'anio' => $entrevista->tax_year]));

            if ($canal === CanalRespuesta::Whatsapp && $pregunta['sensible']) {
                throw new RespuestaRechazada($codigo, __('La pregunta :codigo es sensible: se responde solo en el portal.', ['codigo' => $codigo]));
            }

            $this->validarPersona($entrevista, $pregunta, $datos->personaId);

            if (! $this->motor->situacion($entrevista)->esVisible($codigo, $datos->personaId)) {
                throw new RespuestaRechazada($codigo, __('Esta pregunta ya no aplica con tus respuestas actuales.'));
            }

            $valor = ValorRespuesta::desde($pregunta, $datos->valor)->valor;

            $respuesta = $entrevista->respuestas()
                ->where('pregunta_codigo', $codigo)
                ->where('persona_id', $datos->personaId)
                ->first();

            if ($respuesta !== null && $respuesta->valor === $valor) {
                return $respuesta;
            }

            $atributos = ['valor' => $valor, 'canal' => $canal, 'actor_id' => $actor?->id, 'respondida_en' => now()];

            $respuesta === null
                ? $respuesta = $entrevista->respuestas()->create(['pregunta_codigo' => $codigo, 'persona_id' => $datos->personaId, ...$atributos])
                : $respuesta->update($atributos);

            if ($entrevista->estado === EstadoEntrevista::Confirmada) {
                $entrevista->update(['estado' => EstadoEntrevista::Reabierta]);
            }

            $entrevista->unsetRelation('respuestas');
            $this->sincronizarFormas($entrevista, $this->motor->situacion($entrevista));

            return $respuesta;
        });
    }

    /**
     * @param  array<string, mixed>  $pregunta
     */
    private function validarPersona(Entrevista $entrevista, array $pregunta, ?int $personaId): void
    {
        $codigo = $pregunta['codigo'];

        if (! $pregunta['por_persona']) {
            if ($personaId !== null) {
                throw new RespuestaRechazada($codigo, __('La pregunta :codigo no es por persona.', ['codigo' => $codigo]));
            }

            return;
        }

        if ($personaId === null || ! $entrevista->personas()->whereKey($personaId)->exists()) {
            throw new RespuestaRechazada($codigo, __('La pregunta :codigo necesita una persona de esta entrevista.', ['codigo' => $codigo]));
        }
    }

    /**
     * Declara las formas de los formularios activados. Una forma que dejó de
     * activarse se quita solo si nadie la revisó todavía; si ya la revisó
     * un preparador, queda para que él decida.
     */
    private function sincronizarFormas(Entrevista $entrevista, SituacionEntrevista $situacion): void
    {
        foreach (self::FORMAS as $formulario => $forma) {
            $formulario = (string) $formulario;

            $actual = FormaCliente::query()
                ->where(['user_id' => $entrevista->user_id, 'tax_year' => $entrevista->tax_year, 'forma' => $forma->value])
                ->first();

            if ($situacion->tieneFormulario($formulario)) {
                $actual ?? FormaCliente::query()->create([
                    'user_id' => $entrevista->user_id,
                    'tax_year' => $entrevista->tax_year,
                    'forma' => $forma->value,
                    'estado' => FormState::EnProgreso,
                ]);
            } elseif ($actual !== null && $actual->revisado_en === null) {
                $actual->delete();
            }
        }
    }
}
