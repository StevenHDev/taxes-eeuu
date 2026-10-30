<?php

use App\Http\Controllers\ActivacionCuentaController;
use Illuminate\Support\Facades\Route;

// F2.4: primera entrada desde el enlace de WhatsApp. La URL firmada vence a
// los 60 minutos (App\Services\Consentimiento\EnlaceActivacion); no pide
// sesión porque es justo donde se crea la contraseña.
Route::middleware(['signed', 'throttle:10,1'])->group(function () {
    Route::get('activar/{usuario}', [ActivacionCuentaController::class, 'show'])->name('activacion.show');
    Route::post('activar/{usuario}', [ActivacionCuentaController::class, 'store'])->name('activacion.store');
});
