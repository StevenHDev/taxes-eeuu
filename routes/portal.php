<?php

use App\Http\Controllers\Portal\ConsentimientoController;
use App\Http\Controllers\Portal\EntrevistaController;
use App\Http\Controllers\Portal\PersonaController;
use App\Http\Controllers\PortalChatController;
use App\Http\Controllers\PortalFormularioController;
use App\Http\Middleware\EnsureConsentimiento7216;
use App\Http\Middleware\EnsureEntrevistaNueva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Punto de entrada único: PortalFormularioController::index() manda de
    // vuelta a portal.chat mientras el cliente no tenga ninguna forma
    // declarada todavía (ver decisión de arquitectura del portal seguro).
    // F2.5: quien usa la entrevista nueva entra a su avance.
    Route::get('portal', fn (Request $request) => redirect()->route(
        $request->user()?->usaEntrevistaCatalogo() ? 'portal.entrevista.inicio' : 'portal.formulario',
    ))->name('portal');

    Route::get('portal/chat', [PortalChatController::class, 'index'])->name('portal.chat');
    Route::post('portal/chat', [PortalChatController::class, 'send'])->name('portal.chat.send');

    // F2.4: consentimiento §7216, solo para clientes con la entrevista nueva.
    Route::get('portal/consentimiento', [ConsentimientoController::class, 'show'])->name('portal.consentimiento');
    Route::post('portal/consentimiento', [ConsentimientoController::class, 'store'])->name('portal.consentimiento.store');

    // F2.5: la entrevista nueva, un paso por vez.
    Route::middleware([EnsureEntrevistaNueva::class, EnsureConsentimiento7216::class])
        ->prefix('portal/entrevista')
        ->name('portal.entrevista.')
        ->group(function () {
            Route::get('/', [EntrevistaController::class, 'inicio'])->name('inicio');
            Route::get('personas', [PersonaController::class, 'index'])->name('personas');
            Route::post('personas', [PersonaController::class, 'store'])->name('personas.store');
            Route::delete('personas/{persona}', [PersonaController::class, 'destroy'])->name('personas.destroy');
            Route::get('personas/{persona}/{paso?}', [EntrevistaController::class, 'persona'])
                ->whereIn('paso', ['entrada', 'seguimiento'])
                ->name('persona');
            Route::put('personas/{persona}/{paso}', [EntrevistaController::class, 'guardar'])
                ->whereIn('paso', ['entrada', 'seguimiento'])
                ->name('persona.guardar');
            Route::get('{pantalla}/{paso?}', [EntrevistaController::class, 'paso'])
                ->whereNumber('pantalla')
                ->whereIn('paso', ['entrada', 'seguimiento'])
                ->name('paso');
            Route::put('{pantalla}/{paso}', [EntrevistaController::class, 'guardar'])
                ->whereNumber('pantalla')
                ->whereIn('paso', ['entrada', 'seguimiento'])
                ->name('guardar');
        });

    // Datos y documentos del cliente: piden el consentimiento firmado a
    // quien usa la entrevista nueva (el chat de dudas no).
    Route::middleware(EnsureConsentimiento7216::class)->group(function () {
        Route::get('portal/formulario', [PortalFormularioController::class, 'index'])->name('portal.formulario');
        Route::post('portal/formulario/campos', [PortalFormularioController::class, 'guardarCampo'])->name('portal.formulario.campos.store');
    });
});
