<?php

namespace App\Support;

use App\Enums\FaseConversacion;
use App\Models\AgentePrompt;
use Illuminate\Support\Facades\Cache;

/**
 * Resuelve el contenido de prompt vigente por fase del agente conversacional
 * de WhatsApp — mismo patrón de caché que ParametrosFiscales/TaxFieldCatalog:
 * una sola clave con la versión publicada más reciente, para que
 * invalidate() siga siendo un solo Cache::forget() sin fan-out.
 *
 * "Vigente" = la versión (agente_prompts.version) con mayor número entre las
 * que ya tienen publicada_en <= ahora. Publicar una versión implica escribir
 * una fila por cada FaseConversacion con ese mismo version — ver la
 * migración create_agente_prompts_table.
 */
class AgentePromptVigente
{
    const CACHE_KEY = 'agente_prompt_vigente';

    public static function paraFase(FaseConversacion $fase): ?string
    {
        return self::estado()['contenido'][$fase->value] ?? null;
    }

    public static function version(): ?int
    {
        return self::estado()['version'];
    }

    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{version: int|null, contenido: array<string, string>}
     */
    private static function estado(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $version = AgentePrompt::query()
                ->whereNotNull('publicada_en')
                ->where('publicada_en', '<=', now())
                ->max('version');

            if ($version === null) {
                return ['version' => null, 'contenido' => []];
            }

            return [
                'version' => $version,
                'contenido' => AgentePrompt::query()
                    ->where('version', $version)
                    ->pluck('contenido', 'fase')
                    ->all(),
            ];
        });
    }
}
