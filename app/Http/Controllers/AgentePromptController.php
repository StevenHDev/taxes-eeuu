<?php

namespace App\Http\Controllers;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use App\Support\AgentePromptVigente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Editor de prompts del agente conversacional, por fase — adelantado desde
 * Fase 7 (ver docs/implementar_agente_n8n.md, "Panel de administración del
 * agente"). El versionado sigue siendo el mismo de AgentePrompt/
 * AgentePromptVigente: esta UI reemplaza al archivo+seeder como forma de
 * publicar una versión nueva, no cambia el modelo de datos.
 *
 * Flujo: mientras exista una versión por encima de la vigente sin publicar,
 * esa ES el borrador en curso — guardar reescribe esas mismas 4 filas, nunca
 * crea una versión nueva en cada guardado. Publicar marca esa versión
 * completa (las 4 fases) como vigente de una sola vez — nunca fila por fila,
 * para no dejar una fase huérfana sin contenido en la nueva versión (ver
 * comentario en la migración de agente_prompts).
 */
class AgentePromptController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', AgentePrompt::class);

        $versionVigente = AgentePromptVigente::version();
        $hayBorrador = $this->hayBorrador();
        $versionBorrador = $this->versionBorrador();

        $contenidoBorrador = AgentePrompt::query()
            ->where('version', $versionBorrador)
            ->pluck('contenido', 'fase');

        $fases = collect(FaseConversacion::cases())->map(fn (FaseConversacion $fase) => [
            'fase' => $fase->value,
            'label' => $fase->label(),
            'contenido' => $contenidoBorrador[$fase->value]
                ?? AgentePromptVigente::paraFase($fase)
                ?? '',
        ]);

        return Inertia::render('agente/prompts', [
            'fases' => $fases,
            'versionVigente' => $versionVigente,
            'versionBorrador' => $versionBorrador,
            'hayBorrador' => $hayBorrador,
        ]);
    }

    public function guardarBorrador(Request $request): RedirectResponse
    {
        $this->authorize('update', AgentePrompt::class);

        $fasesValidas = collect(FaseConversacion::cases())->map->value->all();

        $validado = $request->validate([
            'fases' => ['required', 'array', 'size:'.count($fasesValidas)],
            'fases.*' => ['required', 'string'],
        ]);

        abort_unless(
            array_diff($fasesValidas, array_keys($validado['fases'])) === [],
            422,
            'Faltan fases por enviar.',
        );

        $version = $this->versionBorrador();

        foreach ($validado['fases'] as $fase => $contenido) {
            AgentePrompt::query()->updateOrCreate(
                ['version' => $version, 'fase' => $fase],
                ['contenido' => $contenido],
            );
        }

        return back()->with('success', 'Borrador guardado.');
    }

    public function publicar(): RedirectResponse
    {
        $this->authorize('update', AgentePrompt::class);

        abort_unless($this->hayBorrador(), 422, 'No hay ningún borrador para publicar.');

        AgentePrompt::query()->where('version', $this->versionBorrador())->update(['publicada_en' => now()]);
        AgentePromptVigente::invalidate();

        return back()->with('success', 'Versión publicada.');
    }

    /**
     * Hay un borrador real (con filas ya guardadas) solo si existe una
     * versión por encima de la vigente — nunca lo asumas por comparar contra
     * el "próximo slot candidato" de versionBorrador(), que siempre difiere
     * de la vigente por construcción.
     */
    private function hayBorrador(): bool
    {
        return $this->maxVersionTotal() > (int) AgentePromptVigente::version();
    }

    /**
     * La versión en la que se guarda cualquier edición en curso: reutiliza
     * la que ya esté por encima de la vigente si existe, o abre la
     * siguiente si todavía no hay ningún borrador.
     */
    private function versionBorrador(): int
    {
        return $this->hayBorrador()
            ? $this->maxVersionTotal()
            : (int) AgentePromptVigente::version() + 1;
    }

    private function maxVersionTotal(): int
    {
        return (int) AgentePrompt::query()->max('version');
    }
}
