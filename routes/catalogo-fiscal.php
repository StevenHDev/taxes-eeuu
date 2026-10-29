<?php

use App\Http\Controllers\CatalogoFiscal\DocumentoController;
use App\Http\Controllers\CatalogoFiscal\FormularioController;
use App\Http\Controllers\CatalogoFiscal\InicioController;
use App\Http\Controllers\CatalogoFiscal\ParametroController;
use App\Http\Controllers\CatalogoFiscal\PreguntaController;
use App\Http\Controllers\CatalogoFiscal\ReglaController;
use Illuminate\Support\Facades\Route;

// Catálogo fiscal del año (F1.4): preparadores y administradores (ver CatalogoFiscalPolicy).
Route::middleware(['auth', 'verified'])->prefix('catalogo-fiscal')->name('catalogo-fiscal.')->group(function () {
    Route::get('/', InicioController::class)->name('inicio');

    Route::whereNumber('anio')->group(function () {
        Route::get('{anio}/preguntas', [PreguntaController::class, 'index'])->name('preguntas');
        Route::get('{anio}/documentos', [DocumentoController::class, 'index'])->name('documentos');
        Route::get('{anio}/formularios', [FormularioController::class, 'index'])->name('formularios');
        Route::get('{anio}/parametros', [ParametroController::class, 'index'])->name('parametros');
        Route::get('{anio}/reglas', [ReglaController::class, 'index'])->name('reglas');
    });
});
