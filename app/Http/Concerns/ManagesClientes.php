<?php

namespace App\Http\Concerns;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

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
}
