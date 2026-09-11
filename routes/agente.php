<?php

use App\Http\Controllers\AgenteMensajesController;
use App\Http\Controllers\AgentePromptController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('agente', '/agente/mensajes');

    Route::get('agente/mensajes', [AgenteMensajesController::class, 'index'])->name('agente.mensajes.index');

    Route::get('agente/prompts', [AgentePromptController::class, 'index'])->name('agente.prompts.index');
    Route::post('agente/prompts/borrador', [AgentePromptController::class, 'guardarBorrador'])->name('agente.prompts.borrador');
    Route::post('agente/prompts/publicar', [AgentePromptController::class, 'publicar'])->name('agente.prompts.publicar');
});
