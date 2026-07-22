<?php

namespace App\Http\Concerns;

use App\Enums\FormState;
use App\Enums\UserRole;
use App\Models\FormaCliente;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

trait ManagesClientes
{
    /**
     * @return Builder<User>
     */
    protected function clientesVisiblesPara(User $actor, ?string $search = null): Builder
    {
        $query = User::query()->where('role', UserRole::Client);

        if ($actor->role === UserRole::Preparer) {
            $query->where('preparer_id', $actor->id);
        }

        if (filled($search)) {
            $query->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }

        return $query;
    }

    /**
     * Estado general de un cliente a través de todas sus formas: sin datos
     * cargados, en progreso, o todas sus formas completas.
     */
    protected function estadoGeneralDe(User $cliente): string
    {
        if ($cliente->formasCliente->isEmpty()) {
            return 'sin_iniciar';
        }

        if ($cliente->formasCliente->every(fn (FormaCliente $f) => $f->estado === FormState::Completo)) {
            return 'completo';
        }

        return 'en_progreso';
    }

    /**
     * Borra del disco los archivos de todos los documentos de un usuario, antes
     * de eliminarlo — el cascade de la base de datos borra las filas, pero no
     * toca el storage.
     */
    protected function eliminarArchivosDe(User $usuario): void
    {
        $usuario->camposCliente()
            ->whereNotNull('documento_id')
            ->with('documento')
            ->get()
            ->pluck('documento')
            ->filter()
            ->each(fn ($documento) => Storage::disk('local')->delete($documento->file_path));
    }
}
