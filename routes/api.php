<?php

use App\Http\Controllers\Api\CampoClienteController;
use App\Http\Controllers\Api\CatalogoController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\EventoController;
use App\Http\Controllers\Api\WhatsappWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Sin auth:sanctum: la única autenticación posible acá es la firma propia
// del proveedor de WhatsApp vigente (ver WhatsappChannel::validarFirma(),
// resuelta dentro del propio controlador) — un token Sanctum no tiene
// sentido para un webhook que no es un usuario de la plataforma quien lo
// gestiona. GET también se acepta: es el handshake de verificación que Meta
// exige al configurar el webhook (Twilio no lo usa).
Route::match(['get', 'post'], 'whatsapp/webhook', WhatsappWebhookController::class)
    ->name('api.whatsapp.webhook');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('eventos', [EventoController::class, 'store'])->name('api.eventos.store');
    Route::get('catalogo/documentos-extra', [CatalogoController::class, 'documentosExtra'])->name('api.catalogo.documentos-extra');

    Route::prefix('clientes')->name('api.clientes.')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('index');
        Route::post('/', [ClienteController::class, 'store'])->name('store');
        Route::get('/buscar', [ClienteController::class, 'buscar'])->name('buscar');
        Route::get('/{cliente}', [ClienteController::class, 'show'])->name('show');
        Route::get('/{cliente}/documentos', [ClienteController::class, 'documentos'])->name('documentos');
        Route::get('/{cliente}/export', [ClienteController::class, 'export'])->name('export');
        Route::post('/{cliente}/marcar-revisado/{forma}', [ClienteController::class, 'marcarRevisado'])->name('marcar-revisado');
        Route::post('/{cliente}/formas', [ClienteController::class, 'formas'])->name('formas');
        Route::get('/{cliente}/pendientes', [ClienteController::class, 'pendientes'])->name('pendientes');

        Route::get('/{cliente}/campos/{campo}', [CampoClienteController::class, 'historial'])->name('campos.historial');
        Route::match(['put', 'patch'], '/{cliente}/campos/{campo}', [CampoClienteController::class, 'update'])->name('campos.update');
        Route::delete('/{cliente}/campos/{campo}', [CampoClienteController::class, 'destroy'])->name('campos.destroy');
    });
});
