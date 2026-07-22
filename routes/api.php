<?php

use App\Http\Controllers\Api\CampoClienteController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\EventoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('eventos', [EventoController::class, 'store'])->name('api.eventos.store');

    Route::prefix('clientes')->name('api.clientes.')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('index');
        Route::get('/buscar', [ClienteController::class, 'buscar'])->name('buscar');
        Route::get('/{cliente}', [ClienteController::class, 'show'])->name('show');
        Route::get('/{cliente}/documentos', [ClienteController::class, 'documentos'])->name('documentos');
        Route::get('/{cliente}/export', [ClienteController::class, 'export'])->name('export');
        Route::post('/{cliente}/marcar-revisado/{forma}', [ClienteController::class, 'marcarRevisado'])->name('marcar-revisado');

        Route::get('/{cliente}/campos/{campo}', [CampoClienteController::class, 'historial'])->name('campos.historial');
        Route::match(['put', 'patch'], '/{cliente}/campos/{campo}', [CampoClienteController::class, 'update'])->name('campos.update');
        Route::delete('/{cliente}/campos/{campo}', [CampoClienteController::class, 'destroy'])->name('campos.destroy');
    });
});
