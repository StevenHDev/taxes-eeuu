<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\CampoClienteController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DocumentoController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::get('clientes/{cliente}/export', [ClienteController::class, 'export'])->name('clientes.export');
    Route::post('clientes/{cliente}/formas/{forma}/marcar-revisado', [ClienteController::class, 'marcarRevisado'])
        ->name('clientes.marcar-revisado');

    Route::patch('clientes/{cliente}/campos/{campo}', [CampoClienteController::class, 'update'])
        ->name('clientes.campos.update');
    Route::get('clientes/{cliente}/campos/{campo}/historial', [CampoClienteController::class, 'historial'])
        ->name('clientes.campos.historial');
    Route::post('clientes/{cliente}/campos/{campo}/reveal', [CampoClienteController::class, 'reveal'])
        ->middleware([RequirePassword::class, 'throttle:10,1'])
        ->name('clientes.campos.reveal');

    Route::get('documentos/{documento}', [DocumentoController::class, 'show'])
        ->middleware('signed')
        ->name('documentos.show');

    Route::get('api-docs', [ApiDocumentationController::class, 'index'])->name('api-docs.index');
});
