<?php

use App\Http\Controllers\PortalChatController;
use App\Http\Controllers\PortalFormularioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Punto de entrada único: PortalFormularioController::index() manda de
    // vuelta a portal.chat mientras el cliente no tenga ninguna forma
    // declarada todavía (ver decisión de arquitectura del portal seguro).
    Route::redirect('portal', '/portal/formulario');

    Route::get('portal/chat', [PortalChatController::class, 'index'])->name('portal.chat');
    Route::post('portal/chat', [PortalChatController::class, 'send'])->name('portal.chat.send');

    Route::get('portal/formulario', [PortalFormularioController::class, 'index'])->name('portal.formulario');
    Route::post('portal/formulario/campos', [PortalFormularioController::class, 'guardarCampo'])->name('portal.formulario.campos.store');
});
