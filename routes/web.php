<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Provisória: substituída pelo PainelController em T058 (US1).
    Route::view('/', 'painel.index')->name('painel');

    // Rotas por módulo, com um bloco por história (ver tasks.md → Trabalho em dupla).
    require __DIR__.'/candidatos.php';
    require __DIR__.'/vagas.php';
});
