<?php

use App\Http\Controllers\DebidaDiligenciaController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // F5.4: debida diligencia (Form 8867).
    Route::get('clientes/{cliente}/debida-diligencia', [DebidaDiligenciaController::class, 'show'])
        ->whereNumber('cliente')
        ->name('clientes.debida-diligencia.show');
    Route::put('clientes/{cliente}/debida-diligencia/creditos', [DebidaDiligenciaController::class, 'creditos'])
        ->whereNumber('cliente')
        ->name('clientes.debida-diligencia.creditos');
});
