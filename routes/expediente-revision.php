<?php

use App\Enums\Expediente\PuntoRevision;
use App\Http\Controllers\RevisionExpedienteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('clientes/{cliente}/revision', [RevisionExpedienteController::class, 'show'])->whereNumber('cliente')->name('clientes.revision.show');
    Route::put('clientes/{cliente}/revision/estado-civil', [RevisionExpedienteController::class, 'estadoCivil'])->whereNumber('cliente')->name('clientes.revision.estado-civil');
    Route::put('clientes/{cliente}/revision/personas/{persona}', [RevisionExpedienteController::class, 'persona'])->whereNumber(['cliente', 'persona'])->name('clientes.revision.persona');
    Route::put('clientes/{cliente}/revision/puntos/{punto}', [RevisionExpedienteController::class, 'punto'])
        ->whereNumber('cliente')
        ->whereIn('punto', array_map(fn (PuntoRevision $p) => $p->value, array_filter(PuntoRevision::cases(), fn (PuntoRevision $p) => $p->manual())))
        ->name('clientes.revision.punto');
    Route::post('clientes/{cliente}/revision/transicion', [RevisionExpedienteController::class, 'transicion'])->whereNumber('cliente')->name('clientes.revision.transicion');
});
