<?php

namespace App\Services\CatalogoFiscal;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cambios al catálogo fiscal desde la pantalla (F1.5). El historial con el
 * valor anterior y el nuevo lo escribe CatalogoCambioObserver; aquí se
 * aplican las reglas de negocio:
 *
 * - Editar el contenido de algo validado lo vuelve a "sin validar" (6.4):
 *   lo que se validó contra el IRS ya no es lo que está guardado.
 * - Validar exige un enlace a la fuente del IRS.
 * - Nada se borra: se desactiva.
 */
class CatalogoFiscalEditor
{
    /** Atributos que no son contenido: cambiarlos no reabre la validación. */
    private const NO_CONTENIDO = ['orden', 'activo', 'validado_por', 'validado_en', 'updated_at'];

    /**
     * @param  array<string, mixed>  $datos
     * @return bool true si la pieza estaba validada y volvió a "sin validar"
     */
    public function guardar(Model $pieza, array $datos): bool
    {
        return DB::transaction(function () use ($pieza, $datos) {
            $pieza->fill($datos);

            $reabierta = $pieza->exists
                && $pieza->getAttribute('validado_en') !== null
                && array_diff(array_keys($pieza->getDirty()), self::NO_CONTENIDO) !== [];

            if ($reabierta) {
                $pieza->forceFill(['validado_por' => null, 'validado_en' => null]);
            }

            $pieza->save();

            return $reabierta;
        });
    }

    /**
     * Sincroniza las filas hijas (casillas de un documento, líneas de un
     * formulario) por su clave: actualiza las que están, crea las nuevas y
     * desactiva las que ya no vienen. Devuelve true si algo cambió.
     *
     * @param  HasMany<Model, Model>  $relacion
     * @param  list<array<string, mixed>>  $filas
     */
    public function sincronizarHijos(HasMany $relacion, array $filas, string $clave, int $anio): bool
    {
        $existentes = $relacion->getQuery()->withoutGlobalScopes()->get()->keyBy($clave);
        $vistas = [];
        $cambio = false;

        foreach (array_values($filas) as $i => $fila) {
            $hijo = $existentes->get($fila[$clave]) ?? $relacion->make();
            $hijo->fill([...$fila, 'orden' => $i + 1, 'activo' => true]);
            $hijo->setAttribute('tax_year', $anio);

            if (! $hijo->exists || $hijo->isDirty()) {
                $hijo->save();
                $cambio = true;
            }

            $vistas[] = $fila[$clave];
        }

        foreach ($existentes as $valor => $hijo) {
            if (! in_array((string) $valor, array_map('strval', $vistas), true) && $hijo->getAttribute('activo')) {
                $hijo->update(['activo' => false]);
                $cambio = true;
            }
        }

        return $cambio;
    }

    /**
     * Si cambió algo que cuelga de la pieza (casillas, líneas), lo validado
     * ya no corresponde: vuelve a "sin validar". Devuelve true si la reabrió.
     */
    public function reabrirSiValidada(Model $pieza): bool
    {
        if ($pieza->getAttribute('validado_en') === null) {
            return false;
        }

        $pieza->forceFill(['validado_por' => null, 'validado_en' => null])->save();

        return true;
    }

    /**
     * "Marcar como validado contra el IRS": quién y cuándo. Exige la fuente.
     */
    public function validar(Model $pieza, User $actor): void
    {
        if (blank($pieza->getAttribute('fuente_url'))) {
            throw ValidationException::withMessages([
                'fuente_url' => 'Para validar contra el IRS, primero agrega el enlace a la fuente del IRS.',
            ]);
        }

        $pieza->forceFill(['validado_por' => $actor->getKey(), 'validado_en' => now()])->save();
    }

    public function cambiarActivo(Model $pieza, bool $activo): void
    {
        $pieza->forceFill(['activo' => $activo])->save();
    }
}
