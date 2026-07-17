<?php

use App\Http\Controllers\TaxDocumentController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('tax-documents', TaxDocumentController::class);

    Route::get('tax-documents/{taxDocument}/download', [TaxDocumentController::class, 'download'])
        ->name('tax-documents.download');

    Route::post('tax-documents/{taxDocument}/reveal-ssn', [TaxDocumentController::class, 'revealSsn'])
        ->middleware([RequirePassword::class, 'throttle:10,1'])
        ->name('tax-documents.reveal-ssn');
});
