<?php

use App\Http\Controllers\MetaAgenteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('meta-agente', [MetaAgenteController::class, 'index'])->name('meta-agente.index');
    Route::post('meta-agente', [MetaAgenteController::class, 'store'])->name('meta-agente.store');
});
