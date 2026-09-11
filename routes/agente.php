<?php

use App\Http\Controllers\AgenteMensajesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('agente', '/agente/mensajes');

    Route::get('agente/mensajes', [AgenteMensajesController::class, 'index'])->name('agente.mensajes.index');
});
