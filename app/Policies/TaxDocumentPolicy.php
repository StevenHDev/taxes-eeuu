<?php

namespace App\Policies;

use App\Models\TaxDocument;
use App\Models\User;

class TaxDocumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TaxDocument $taxDocument): bool
    {
        return $this->owns($user, $taxDocument);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TaxDocument $taxDocument): bool
    {
        return $this->owns($user, $taxDocument);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TaxDocument $taxDocument): bool
    {
        return $this->owns($user, $taxDocument);
    }

    protected function owns(User $user, TaxDocument $taxDocument): bool
    {
        if ($taxDocument->user_id === $user->id) {
            return true;
        }

        return $user->role === 'preparer' && $taxDocument->user->preparer_id === $user->id;
    }
}
