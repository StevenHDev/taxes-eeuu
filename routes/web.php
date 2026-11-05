<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/portal.php';
require __DIR__.'/activacion.php';
require __DIR__.'/clientes.php';
require __DIR__.'/expediente-ficha.php';
require __DIR__.'/expediente-revision.php';
require __DIR__.'/expediente-hoja.php';
require __DIR__.'/expediente-debida-diligencia.php';
require __DIR__.'/catalogo-fiscal.php';
require __DIR__.'/usuarios.php';
require __DIR__.'/bitacora.php';
require __DIR__.'/agente.php';
require __DIR__.'/proceso.php';
require __DIR__.'/meta_agente.php';
