<?php

use App\Http\Controllers\CatalogoFiscal\DocumentoController;
use App\Http\Controllers\CatalogoFiscal\FormularioController;
use App\Http\Controllers\CatalogoFiscal\InicioController;
use App\Http\Controllers\CatalogoFiscal\ParametroController;
use App\Http\Controllers\CatalogoFiscal\PiezaController;
use App\Http\Controllers\CatalogoFiscal\PreguntaController;
use App\Http\Controllers\CatalogoFiscal\ReglaController;
use Illuminate\Support\Facades\Route;

// Catálogo fiscal del año: lo ven preparadores y administradores (F1.4) y lo
// edita solo el administrador (F1.5). Ver CatalogoFiscalPolicy y los Form Requests.
Route::middleware(['auth', 'verified'])->prefix('catalogo-fiscal')->name('catalogo-fiscal.')->group(function () {
    Route::get('/', InicioController::class)->name('inicio');

    Route::whereNumber('anio')->group(function () {
        Route::get('{anio}/preguntas', [PreguntaController::class, 'index'])->name('preguntas');
        Route::post('{anio}/preguntas', [PreguntaController::class, 'store'])->name('preguntas.store');
        Route::patch('{anio}/preguntas/{pregunta}', [PreguntaController::class, 'update'])->name('preguntas.update');

        Route::get('{anio}/documentos', [DocumentoController::class, 'index'])->name('documentos');
        Route::post('{anio}/documentos', [DocumentoController::class, 'store'])->name('documentos.store');
        Route::patch('{anio}/documentos/{documento}', [DocumentoController::class, 'update'])->name('documentos.update');

        Route::get('{anio}/formularios', [FormularioController::class, 'index'])->name('formularios');
        Route::post('{anio}/formularios', [FormularioController::class, 'store'])->name('formularios.store');
        Route::patch('{anio}/formularios/{formulario}', [FormularioController::class, 'update'])->name('formularios.update');

        Route::get('{anio}/parametros', [ParametroController::class, 'index'])->name('parametros');
        Route::patch('{anio}/parametros/{parametro}', [ParametroController::class, 'update'])->name('parametros.update');

        Route::get('{anio}/reglas', [ReglaController::class, 'index'])->name('reglas');
        Route::post('{anio}/reglas', [ReglaController::class, 'store'])->name('reglas.store');
        Route::patch('{anio}/reglas/{regla}', [ReglaController::class, 'update'])->name('reglas.update');

        Route::whereIn('tipo', ['preguntas', 'documentos', 'formularios', 'reglas', 'parametros'])->whereNumber('id')->group(function () {
            Route::post('{anio}/{tipo}/{id}/validar', [PiezaController::class, 'validar'])->name('validar');
            Route::patch('{anio}/{tipo}/{id}/activo', [PiezaController::class, 'activo'])->name('activo');
        });
    });
});
