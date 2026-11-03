<?php

use App\Http\Controllers\ApiDocumentationController;
use App\Http\Controllers\CampoClienteController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DeterminacionFiscalController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\EntrevistaDocumentoController;
use App\Http\Controllers\EntrevistaRespuestaController;
use App\Http\Controllers\ExcepcionController;
use App\Http\Controllers\VisorDocumentoController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::post('clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::get('clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show');
    Route::delete('clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
    Route::get('clientes/{cliente}/export', [ClienteController::class, 'export'])->name('clientes.export');
    Route::get('clientes/{cliente}/conversacion-whatsapp', [ClienteController::class, 'conversacionWhatsapp'])
        ->name('clientes.conversacion-whatsapp');
    Route::post('clientes/{cliente}/whatsapp/tomar-control', [ClienteController::class, 'tomarControlWhatsapp'])
        ->name('clientes.whatsapp.tomar-control');
    Route::post('clientes/{cliente}/whatsapp/devolver-control', [ClienteController::class, 'devolverControlWhatsapp'])
        ->name('clientes.whatsapp.devolver-control');
    Route::post('clientes/{cliente}/whatsapp/enviar', [ClienteController::class, 'enviarMensajeWhatsapp'])
        ->name('clientes.whatsapp.enviar');
    Route::post('clientes/{cliente}/formas/{forma}/marcar-revisado', [ClienteController::class, 'marcarRevisado'])
        ->name('clientes.marcar-revisado');
    Route::post('clientes/{cliente}/determinaciones', [DeterminacionFiscalController::class, 'store'])
        ->name('clientes.determinaciones.store');
    Route::post('clientes/{cliente}/nivel-riesgo', [ClienteController::class, 'establecerNivelRiesgo'])
        ->name('clientes.nivel-riesgo.store');
    Route::delete('clientes/{cliente}/nivel-riesgo', [ClienteController::class, 'limpiarNivelRiesgo'])
        ->name('clientes.nivel-riesgo.destroy');

    Route::get('clientes/{cliente}/campos/{campo}/historial', [CampoClienteController::class, 'historial'])
        ->name('clientes.campos.historial');
    Route::post('clientes/{cliente}/campos/{campo}/reveal', [CampoClienteController::class, 'reveal'])
        ->middleware([RequirePassword::class, 'throttle:10,1'])
        ->name('clientes.campos.reveal');

    // F2.9: la entrevista del cliente vista por el preparador.
    Route::put('clientes/{cliente}/entrevista/respuestas/{respuesta}', [EntrevistaRespuestaController::class, 'update'])
        ->name('clientes.entrevista.respuestas.update');
    Route::get('clientes/{cliente}/entrevista/respuestas/{respuesta}/historial', [EntrevistaRespuestaController::class, 'historial'])
        ->name('clientes.entrevista.respuestas.historial');
    Route::post('clientes/{cliente}/entrevista/respuestas/{respuesta}/revelar', [EntrevistaRespuestaController::class, 'reveal'])
        ->middleware([RequirePassword::class, 'throttle:10,1'])
        ->name('clientes.entrevista.respuestas.revelar');

    // F3.4: el preparador revisa los documentos del checklist.
    Route::patch('clientes/{cliente}/entrevista/documentos/{item}', [EntrevistaDocumentoController::class, 'update'])
        ->whereNumber('item')
        ->name('clientes.entrevista.documentos.update');

    // F4.6: visor de documento.
    Route::get('clientes/{cliente}/documentos/{item}/visor', [VisorDocumentoController::class, 'show'])
        ->whereNumber('item')
        ->name('clientes.documentos.visor');
    Route::get('clientes/{cliente}/documentos/{item}/visor/paginas/{lectura}/{pagina}', [VisorDocumentoController::class, 'pagina'])
        ->whereNumber(['item', 'lectura', 'pagina'])
        ->name('clientes.documentos.visor.pagina');
    Route::post('clientes/{cliente}/documentos/{item}/visor/datos/{dato}/revelar', [VisorDocumentoController::class, 'reveal'])
        ->whereNumber(['item', 'dato'])
        ->middleware([RequirePassword::class, 'throttle:10,1'])
        ->name('clientes.documentos.visor.revelar');
    Route::patch('clientes/{cliente}/documentos/{item}/visor/datos/{dato}', [VisorDocumentoController::class, 'corregir'])
        ->whereNumber(['item', 'dato'])
        ->name('clientes.documentos.visor.corregir');

    Route::get('documentos/{documento}', [DocumentoController::class, 'show'])
        ->middleware('signed')
        ->name('documentos.show');

    Route::get('api-docs', [ApiDocumentationController::class, 'index'])->name('api-docs.index');
    Route::get('api-docs/openapi.yaml', [ApiDocumentationController::class, 'openapi'])->name('api-docs.openapi');

    // F4.7: cola de excepciones.
    Route::get('excepciones', [ExcepcionController::class, 'index'])->name('clientes.excepciones.index');
    Route::post('excepciones/{excepcion}/aceptar', [ExcepcionController::class, 'aceptar'])
        ->whereNumber('excepcion')
        ->name('clientes.excepciones.aceptar');
    Route::post('excepciones/{excepcion}/consultar', [ExcepcionController::class, 'consultar'])
        ->whereNumber('excepcion')
        ->name('clientes.excepciones.consultar');
});
