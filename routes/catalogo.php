<?php

use App\Http\Controllers\CatalogoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('catalogo', [CatalogoController::class, 'index'])->name('catalogo.index');
    Route::post('catalogo', [CatalogoController::class, 'store'])->name('catalogo.store');
    Route::patch('catalogo/{campo}', [CatalogoController::class, 'update'])->name('catalogo.update');
    Route::delete('catalogo/{campo}', [CatalogoController::class, 'destroy'])->name('catalogo.destroy');
});
