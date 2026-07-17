<?php

use App\Http\Controllers\Api\TaxDocumentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum'])
    ->prefix('tax-documents')
    ->name('api.tax-documents.')
    ->group(function () {
        Route::get('/', [TaxDocumentController::class, 'index'])->name('index');
        Route::post('/', [TaxDocumentController::class, 'store'])->name('store');
        Route::get('/{tax_document}', [TaxDocumentController::class, 'show'])->name('show');
        Route::match(['put', 'patch'], '/{tax_document}', [TaxDocumentController::class, 'update'])->name('update');
        Route::delete('/{tax_document}', [TaxDocumentController::class, 'destroy'])->name('destroy');
        Route::get('/{tax_document}/download', [TaxDocumentController::class, 'download'])->name('download');
        Route::post('/{tax_document}/reveal-ssn', [TaxDocumentController::class, 'revealSsn'])
            ->middleware('throttle:10,1')
            ->name('reveal-ssn');
    });
