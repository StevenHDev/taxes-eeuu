<?php

use App\Http\Controllers\Portal\ConsentimientoController;
use App\Http\Controllers\PortalChatController;
use App\Http\Controllers\PortalFormularioController;
use App\Http\Middleware\EnsureConsentimiento7216;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Punto de entrada único: PortalFormularioController::index() manda de
    // vuelta a portal.chat mientras el cliente no tenga ninguna forma
    // declarada todavía (ver decisión de arquitectura del portal seguro).
    Route::redirect('portal', '/portal/formulario');

    Route::get('portal/chat', [PortalChatController::class, 'index'])->name('portal.chat');
    Route::post('portal/chat', [PortalChatController::class, 'send'])->name('portal.chat.send');

    // F2.4: consentimiento §7216, solo para clientes con la entrevista nueva.
    Route::get('portal/consentimiento', [ConsentimientoController::class, 'show'])->name('portal.consentimiento');
    Route::post('portal/consentimiento', [ConsentimientoController::class, 'store'])->name('portal.consentimiento.store');

    // Datos y documentos del cliente: piden el consentimiento firmado a
    // quien usa la entrevista nueva (el chat de dudas no).
    Route::middleware(EnsureConsentimiento7216::class)->group(function () {
        Route::get('portal/formulario', [PortalFormularioController::class, 'index'])->name('portal.formulario');
        Route::post('portal/formulario/campos', [PortalFormularioController::class, 'guardarCampo'])->name('portal.formulario.campos.store');
    });
});
