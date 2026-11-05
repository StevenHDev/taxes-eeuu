<?php

use App\Http\Controllers\HojaTaxwiseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // F5.5: hoja de trabajo para TaxWise.
    Route::get('clientes/{cliente}/hoja-taxwise', [HojaTaxwiseController::class, 'show'])
        ->whereNumber('cliente')
        ->name('clientes.hoja-taxwise.show');
    Route::get('clientes/{cliente}/hoja-taxwise/csv', [HojaTaxwiseController::class, 'csv'])
        ->whereNumber('cliente')
        ->name('clientes.hoja-taxwise.csv');
});
