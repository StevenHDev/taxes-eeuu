<?php

use App\Http\Controllers\Portal\ConfirmarEntrevistaController;
use App\Http\Controllers\Portal\ConsentimientoController;
use App\Http\Controllers\Portal\DocumentoEntrevistaController;
use App\Http\Controllers\Portal\EntrevistaController;
use App\Http\Controllers\Portal\PersonaController;
use App\Http\Controllers\PortalChatController;
use App\Http\Middleware\EnsureConsentimiento7216;
use App\Http\Middleware\EnsureEntrevistaNueva;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // Punto de entrada único del portal: el avance de la entrevista.
    Route::get('portal', fn () => redirect()->route('portal.entrevista.inicio'))->name('portal');

    Route::get('portal/chat', [PortalChatController::class, 'index'])->name('portal.chat');
    Route::post('portal/chat', [PortalChatController::class, 'send'])->name('portal.chat.send');

    // F2.4: consentimiento §7216.
    Route::get('portal/consentimiento', [ConsentimientoController::class, 'show'])->name('portal.consentimiento');
    Route::post('portal/consentimiento', [ConsentimientoController::class, 'store'])->name('portal.consentimiento.store');

    // F2.5: la entrevista nueva, un paso por vez.
    Route::middleware([EnsureEntrevistaNueva::class, EnsureConsentimiento7216::class])
        ->prefix('portal/entrevista')
        ->name('portal.entrevista.')
        ->group(function () {
            Route::get('/', [EntrevistaController::class, 'inicio'])->name('inicio');
            Route::get('resumen', [EntrevistaController::class, 'resumen'])->name('resumen');
            Route::post('confirmar', ConfirmarEntrevistaController::class)->name('confirmar');
            Route::get('documentos', [DocumentoEntrevistaController::class, 'index'])->name('documentos');
            Route::middleware('throttle:30,1')->group(function () {
                Route::post('documentos/otro', [DocumentoEntrevistaController::class, 'otroDocumento'])->name('documentos.otro.store');
                Route::post('documentos/{item}/archivos', [DocumentoEntrevistaController::class, 'store'])
                    ->whereNumber('item')
                    ->name('documentos.archivos.store');
                Route::post('documentos/{item}/no-aplica', [DocumentoEntrevistaController::class, 'noAplica'])
                    ->whereNumber('item')
                    ->name('documentos.no-aplica');
                Route::post('documentos/{item}/otro', [DocumentoEntrevistaController::class, 'agregarOtro'])
                    ->whereNumber('item')
                    ->name('documentos.otro.agregar');
            });
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
});
