<?php

use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::patch('usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
    Route::patch('usuarios/{usuario}/restaurar', [UsuarioController::class, 'restore'])
        ->withTrashed()
        ->name('usuarios.restore');
    Route::patch('usuarios/{usuario}/prueba', [UsuarioController::class, 'marcarPrueba'])
        ->withTrashed()
        ->name('usuarios.prueba');
    Route::delete('usuarios/{usuario}/definitivo', [UsuarioController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('usuarios.force-destroy');
});
