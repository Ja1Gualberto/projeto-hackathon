<?php

use App\Http\Controllers\VagaController;
use App\Http\Controllers\VagaStatusController;
use Illuminate\Support\Facades\Route;

/*
 * Rotas de vagas e seleção, incluídas no grupo `auth` de routes/web.php.
 * Cada história adiciona as suas rotas em um bloco próprio:
 *
 * // US3 — vagas
 * // US4 — seleção
 * // US5 — anotações
 *
 * Rotas com {candidato:codigo} usam ->withoutScopedBindings() (research R9).
 * Sempre restrinja {vaga} com ->whereNumber('vaga'), para /vagas/criar não depender da ordem.
 */

// US3 — vagas
Route::get('/vagas', [VagaController::class, 'index'])->name('vagas.index');
Route::get('/vagas/criar', [VagaController::class, 'create'])->name('vagas.create');
Route::post('/vagas', [VagaController::class, 'store'])->name('vagas.store');
Route::get('/vagas/{vaga}', [VagaController::class, 'show'])
    ->whereNumber('vaga')->name('vagas.show');
Route::get('/vagas/{vaga}/editar', [VagaController::class, 'edit'])
    ->whereNumber('vaga')->name('vagas.edit');
Route::put('/vagas/{vaga}', [VagaController::class, 'update'])
    ->whereNumber('vaga')->name('vagas.update');
Route::patch('/vagas/{vaga}/status', [VagaStatusController::class, 'update'])
    ->whereNumber('vaga')->name('vagas.status');
