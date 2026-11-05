<?php

use App\Http\Controllers\FichaClienteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('clientes/{cliente}/entrevista', [FichaClienteController::class, 'entrevista'])
        ->whereNumber('cliente')->name('clientes.ficha.entrevista');
    Route::get('clientes/{cliente}/documentos', [FichaClienteController::class, 'documentos'])
        ->whereNumber('cliente')->name('clientes.ficha.documentos');
    Route::get('clientes/{cliente}/excepciones', [FichaClienteController::class, 'excepciones'])
        ->whereNumber('cliente')->name('clientes.ficha.excepciones');
    Route::get('clientes/{cliente}/historial', [FichaClienteController::class, 'historial'])
        ->whereNumber('cliente')->name('clientes.ficha.historial');
});
