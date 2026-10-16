<?php

/**
 * Mensajes del flujo "olvidé mi contraseña" / definir contraseña (Fortify).
 * Mismo problema que lang/es/validation.php: sin este archivo, con
 * APP_LOCALE=es y APP_FALLBACK_LOCALE=es, estos mensajes se mostraban sin
 * traducir.
 */
return [

    'reset' => 'Tu contraseña fue actualizada.',
    'sent' => 'Te enviamos por correo el link para definir tu contraseña.',
    'throttled' => 'Espera un momento antes de volver a intentarlo.',
    'token' => 'Este link para definir la contraseña ya no es válido — pide uno nuevo.',
    'user' => 'No encontramos ninguna cuenta con ese correo electrónico.',

];
