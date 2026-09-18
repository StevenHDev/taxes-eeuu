<?php

namespace App\Services\WhatsappAgent;

use App\Enums\TipoPromptActivoStep;
use App\Models\CampoCatalogo;
use App\Models\CampoCliente;
use App\Models\PromptActivoStep;

/**
 * Resuelve, a partir de la lista PromptActivoStep (la misma fuente de verdad
 * que ActivosPromptComposer usa para compilar el prompt), cuál es el próximo
 * campo transversal ACTIVO que el agente debe pedir — para que
 * AgenteToolService::pendientes() lo entregue ya resuelto en
 * `siguiente_activo`, en vez de dejar que el modelo recalcule el orden y las
 * condiciones (casado/soltero, bifurcación de empleo) turno a turno a partir
 * de instrucciones en prosa.
 *
 * Encontrado evaluando la conversación real con 3213445027 (2026-09-18): con
 * ese cálculo íntegramente en el prompt, el modelo (gpt-5.6-luna) perdía el
 * orden fijo, y hasta repetía "¿eres empleado?" pese a que el prompt lo
 * prohibía explícitamente. Mover el cálculo a código lo hace imposible de
 * romper, sin importar qué tan cargado esté el resto del prompt — el modelo
 * solo necesita leer un campo ya resuelto, no derivarlo.
 *
 * @internal solo AgenteToolService debe invocarlo.
 */
class ActivosResolver
{
    /**
     * @param  array<int, array<string, mixed>>  $pendientes  ya calculado por TaxFieldCatalog::pendientesPara() para este cliente
     * @return array<string, mixed>|null
     */
    public function siguiente(int $taxYear, int $clienteId, array $pendientes): ?array
    {
        /** @var array<string, array<string, mixed>> $pendientesPorCampo */
        $pendientesPorCampo = collect($pendientes)
            ->filter(fn (array $p) => $p['forma'] === CampoCatalogo::TRANSVERSAL)
            ->keyBy('campo')
            ->all();

        foreach (PromptActivoStep::query()->orderBy('orden')->get() as $paso) {
            $resultado = match ($paso->tipo) {
                TipoPromptActivoStep::Bifurcacion => $this->resolverBifurcacion($paso, $pendientesPorCampo),
                TipoPromptActivoStep::Condicional => $this->resolverCondicional($paso, $pendientesPorCampo, $taxYear, $clienteId),
                TipoPromptActivoStep::Grupo => $this->resolverGrupo($paso, $pendientesPorCampo),
                TipoPromptActivoStep::Simple, TipoPromptActivoStep::DocumentoConNota => $pendientesPorCampo[(string) $paso->campo] ?? null,
            };

            if ($resultado !== null) {
                return $resultado;
            }
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $pendientesPorCampo
     * @return array<string, mixed>|null
     */
    private function resolverBifurcacion(PromptActivoStep $paso, array $pendientesPorCampo): ?array
    {
        $campoSi = $pendientesPorCampo[(string) $paso->campo_si] ?? null;
        $campoNo = $pendientesPorCampo[(string) $paso->campo_no] ?? null;

        // Ninguno de los dos sigue pendiente: la bifurcación ya se resolvió
        // (uno quedó recibido, el otro no_aplica) — nada que preguntar acá.
        if ($campoSi === null && $campoNo === null) {
            return null;
        }

        return [
            'tipo' => 'bifurcacion',
            'etiqueta' => $paso->etiqueta,
            'pregunta' => $paso->pregunta,
            'campo_si' => $campoSi,
            'campo_no' => $campoNo,
        ];
    }

    /**
     * Generaliza resolverBifurcacion() a N miembros: mientras quede al menos
     * un miembro del grupo todavía pendiente (ni recibido ni no_aplica), se
     * devuelve la pregunta compuesta con solo los miembros que faltan — no
     * se vuelve a preguntar por los ya resueltos dentro del mismo grupo.
     *
     * @param  array<string, array<string, mixed>>  $pendientesPorCampo
     * @return array<string, mixed>|null
     */
    private function resolverGrupo(PromptActivoStep $paso, array $pendientesPorCampo): ?array
    {
        $miembrosPendientes = collect($paso->miembros ?? [])
            ->map(fn (string $campo) => $pendientesPorCampo[$campo] ?? null)
            ->filter()
            ->values();

        if ($miembrosPendientes->isEmpty()) {
            return null;
        }

        return [
            'tipo' => 'grupo',
            'etiqueta' => $paso->etiqueta,
            'pregunta' => $paso->pregunta,
            'miembros' => $miembrosPendientes->all(),
        ];
    }

    /**
     * No es un motor de condiciones genérico — evalúa explícitamente cada
     * caso conocido (igual que ActivosPromptComposer::salvaguardaPara ya
     * distingue por tipo); un condicional nuevo necesita su propio caso acá.
     *
     * @param  array<string, array<string, mixed>>  $pendientesPorCampo
     * @return array<string, mixed>|null
     */
    private function resolverCondicional(PromptActivoStep $paso, array $pendientesPorCampo, int $taxYear, int $clienteId): ?array
    {
        $pendiente = $pendientesPorCampo[(string) $paso->campo] ?? null;

        if ($pendiente === null) {
            return null;
        }

        $aplica = match ($paso->campo) {
            'info_conyuge' => $this->esCasado($taxYear, $clienteId),
            'cuentas_extranjero_detalle' => $this->respondioSi($taxYear, $clienteId, 'cuentas_extranjero'),
            default => true,
        };

        return $aplica ? $pendiente : null;
    }

    private function esCasado(int $taxYear, int $clienteId): bool
    {
        $estadoCivil = CampoCliente::query()
            ->where('user_id', $clienteId)
            ->where('tax_year', $taxYear)
            ->where('forma', CampoCatalogo::TRANSVERSAL)
            ->where('campo', 'estado_civil')
            ->first();

        return (bool) ($estadoCivil?->valor_texto['casado_al_31_dic'] ?? false);
    }

    /**
     * Compara contra el literal exacto "si" que guarda EventoRecoleccionService
     * para las compuertas sí/no de compliance (ver validarString) — nunca
     * "Sí", "afirmativo" ni ninguna otra variante.
     */
    private function respondioSi(int $taxYear, int $clienteId, string $campo): bool
    {
        $respuesta = CampoCliente::query()
            ->where('user_id', $clienteId)
            ->where('tax_year', $taxYear)
            ->where('forma', CampoCatalogo::TRANSVERSAL)
            ->where('campo', $campo)
            ->first();

        return $respuesta?->valor_texto === 'si';
    }
}
