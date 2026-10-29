<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Roles que deben tener MFA
    |--------------------------------------------------------------------------
    |
    | F0.1 del documento madre (mínimo T1): estos roles solo entran al panel
    | con la verificación en dos pasos confirmada o una passkey registrada
    | (ver EnsureStaffHasMfa). Los clientes no, por ahora: pendiente de L9.
    |
    */

    'mfa_roles' => ['preparer', 'administrator'],

];
