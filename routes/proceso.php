<?php

use App\Http\Controllers\ProcesoController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('proceso', [ProcesoController::class, 'show'])->name('proceso.show');
    Route::get('proceso/raw', [ProcesoController::class, 'raw'])->name('proceso.raw');
});
