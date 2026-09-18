<?php

namespace App\Console\Commands;

use App\Enums\FaseConversacion;
use App\Enums\OrigenAnalisisMetaAgente;
use App\Models\MetaAgenteReporte;
use App\Models\User;
use App\Models\WhatsappMensaje;
use App\Services\MetaAgente\ConversacionAnalizadorService;
use App\Services\WhatsappAgent\EstadoConversacionResolver;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;

/**
 * Sin --telefono: corrida automática diaria (ver routes/console.php) —
 * analiza cada conversación con mensajes nuevos desde su último reporte,
 * filtrando por META_AGENTE_ALCANCE_DIARIO. Con --telefono (uno o repetido):
 * análisis puntual de la(s) conversación(es) completa(s) indicada(s), sin
 * importar el alcance diario — mismo camino que usa el panel (ver
 * AnalizarConversacionAgenteJob), útil para correrlo a mano por SSH.
 */
class AnalizarConversacionesAgenteCommand extends Command
{
    protected $signature = 'meta-agente:analizar
        {--telefono=* : Uno o más teléfonos a analizar puntualmente (conversación completa)}
        {--disparado-por= : ID del usuario que dispara el análisis manual (opcional)}';

    protected $description = 'Corre el meta-agente sobre conversaciones puntuales, o —sin --telefono— sobre todas las que tuvieron actividad nueva desde su último análisis.';

    public function handle(ConversacionAnalizadorService $analizador, EstadoConversacionResolver $resolver): int
    {
        $telefonosManual = array_values(array_filter((array) $this->option('telefono'), fn ($t) => $t !== ''));

        if ($telefonosManual !== []) {
            $disparadoPorId = $this->option('disparado-por');
            $disparadoPor = $disparadoPorId ? User::query()->find((int) $disparadoPorId) : null;

            foreach ($telefonosManual as $telefono) {
                $this->ejecutar($analizador, (string) $telefono, OrigenAnalisisMetaAgente::Manual, $disparadoPor);
            }

            return self::SUCCESS;
        }

        $analizados = 0;

        foreach ($this->telefonosParaCorridaDiaria($resolver) as $telefono => $desde) {
            $this->ejecutar($analizador, $telefono, OrigenAnalisisMetaAgente::Programado, null, $desde);
            $analizados++;
        }

        $this->info("Meta-agente: {$analizados} conversación(es) analizada(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<string, ?CarbonInterface> teléfono => fecha desde la que analizar (null = conversación completa, nunca antes analizada)
     */
    private function telefonosParaCorridaDiaria(EstadoConversacionResolver $resolver): array
    {
        $alcance = config('meta_agente.alcance_diario');
        $resultado = [];

        foreach (WhatsappMensaje::query()->select('telefono')->distinct()->pluck('telefono') as $telefono) {
            $ultimoReporte = MetaAgenteReporte::query()->where('telefono', $telefono)->latest('rango_hasta')->first();
            $ultimoMensaje = WhatsappMensaje::query()->where('telefono', $telefono)->latest('created_at')->value('created_at');

            if ($ultimoReporte !== null && $ultimoMensaje !== null && $ultimoMensaje->lessThanOrEqualTo($ultimoReporte->rango_hasta)) {
                continue;
            }

            if ($alcance === 'cierre') {
                $cliente = WhatsappMensaje::query()->where('telefono', $telefono)->latest('created_at')->first()?->cliente;

                if ($cliente === null || $resolver->resolver($cliente) !== FaseConversacion::Cierre) {
                    continue;
                }
            }

            $resultado[$telefono] = $ultimoReporte?->rango_hasta;
        }

        return $resultado;
    }

    private function ejecutar(
        ConversacionAnalizadorService $analizador,
        string $telefono,
        OrigenAnalisisMetaAgente $origen,
        ?User $disparadoPor,
        ?CarbonInterface $desde = null,
    ): void {
        $reporte = $analizador->analizar($telefono, $origen, $disparadoPor, $desde);

        $this->line($reporte !== null
            ? "{$telefono}: ".count($reporte->hallazgos).' hallazgo(s).'
            : "{$telefono}: sin mensajes que analizar.");
    }
}
