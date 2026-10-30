<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        // Restablecerla desde el correo también cuenta como definirla: desde
        // ese momento el agente ya no manda enlaces de activación (F2.4).
        $user->forceFill([
            'password' => $input['password'],
            'contrasena_definida_en' => $user->contrasena_definida_en ?? now(),
        ])->save();
    }
}
