<?php

use App\Http\Controllers\DatosSinDocumentoController;
use Illuminate\Support\Facades\Route;

// F6: el borrador del 1040. 6.5 crea el archivo con el respaldo manual sin documento;
// 6.7a (pestaña) y 6.8c (PDF) agregan sus rutas aquí en olas posteriores.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('clientes/{cliente}/borrador/sin-documento', [DatosSinDocumentoController::class, 'store'])
        ->whereNumber('cliente')
        ->name('clientes.borrador.sin-documento.store');
    Route::put('clientes/{cliente}/borrador/sin-documento/{dato}', [DatosSinDocumentoController::class, 'update'])
        ->whereNumber(['cliente', 'dato'])
        ->name('clientes.borrador.sin-documento.update');
    Route::delete('clientes/{cliente}/borrador/sin-documento/{dato}', [DatosSinDocumentoController::class, 'destroy'])
        ->whereNumber(['cliente', 'dato'])
        ->name('clientes.borrador.sin-documento.destroy');
});
