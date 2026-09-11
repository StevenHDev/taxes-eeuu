<?php

use App\Http\Controllers\AgenteBaseConocimientoController;
use App\Http\Controllers\AgenteMensajesController;
use App\Http\Controllers\AgentePromptController;
use App\Http\Controllers\AgenteToolController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('agente', '/agente/mensajes');

    Route::get('agente/mensajes', [AgenteMensajesController::class, 'index'])->name('agente.mensajes.index');

    Route::get('agente/prompts', [AgentePromptController::class, 'index'])->name('agente.prompts.index');
    Route::post('agente/prompts/borrador', [AgentePromptController::class, 'guardarBorrador'])->name('agente.prompts.borrador');
    Route::post('agente/prompts/publicar', [AgentePromptController::class, 'publicar'])->name('agente.prompts.publicar');

    Route::get('agente/tools', [AgenteToolController::class, 'index'])->name('agente.tools.index');
    Route::patch('agente/tools', [AgenteToolController::class, 'update'])->name('agente.tools.update');

    Route::get('agente/base-conocimiento', [AgenteBaseConocimientoController::class, 'index'])->name('agente.base-conocimiento.index');
    Route::post('agente/base-conocimiento', [AgenteBaseConocimientoController::class, 'store'])->name('agente.base-conocimiento.store');
    Route::delete('agente/base-conocimiento/{documento}', [AgenteBaseConocimientoController::class, 'destroy'])->name('agente.base-conocimiento.destroy');
});
