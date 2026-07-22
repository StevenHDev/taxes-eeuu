<?php

namespace App\Http\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

trait ManagesClientes
{
    /**
     * @return Builder<User>
     */
    protected function clientesVisiblesPara(User $actor): Builder
    {
        $query = User::query()->where('role', UserRole::Client);

        if ($actor->role === UserRole::Preparer) {
            return $query->where('preparer_id', $actor->id);
        }

        return $query;
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
