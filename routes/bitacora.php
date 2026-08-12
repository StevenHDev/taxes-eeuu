<?php

use App\Http\Controllers\BitacoraController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');
});
