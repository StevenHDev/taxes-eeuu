<?php

namespace App\Support;

use App\Enums\FaseConversacion;
use App\Models\AgenteToolEstado;
use Illuminate\Support\Facades\Cache;

/**
 * Resuelve si una tool está activa para una fase del agente conversacional —
 * mismo patrón de caché de una sola clave que AgentePromptVigente. Solo se
 * cachean las filas realmente desactivadas: una tool sin fila en
 * agente_tool_estados está activa por defecto (ver la migración).
 */
class AgenteToolEstados
{
    const CACHE_KEY = 'agente_tool_estados_desactivados';

    public static function activo(FaseConversacion $fase, string $toolName): bool
    {
        return ! in_array(self::clave($fase, $toolName), self::desactivados(), true);
    }

    public static function invalidate(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<int, string>
     */
    private static function desactivados(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => AgenteToolEstado::query()
                ->where('activo', false)
                ->get()
                ->map(fn (AgenteToolEstado $e) => self::clave(FaseConversacion::from($e->fase), $e->tool_name))
                ->all(),
        );
    }

    private static function clave(FaseConversacion $fase, string $toolName): string
    {
        return "{$fase->value}:{$toolName}";
    }
}
