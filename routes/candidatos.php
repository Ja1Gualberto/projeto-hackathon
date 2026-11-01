<?php

use App\Http\Controllers\CandidatoController;
use App\Http\Controllers\CompetenciaController;
use App\Http\Controllers\ConfirmacaoImportacaoController;
use App\Http\Controllers\CurriculoController;
use App\Http\Controllers\ImportacaoCurriculoController;
use Illuminate\Support\Facades\Route;

/*
 * Rotas do banco de talentos, incluídas no grupo `auth` de routes/web.php.
 * Cada história adiciona as suas rotas em um bloco próprio:
 *
 * // US1 — gestão do banco
 * // US2 — importação
 * // US5 — anotações
 * // US6 — LGPD
 * // US7 — energia
 *
 * Sempre restrinja {candidato} com ->whereNumber('candidato').
 */

// US1 — gestão do banco
Route::get('/candidatos', [CandidatoController::class, 'index'])->name('candidatos.index');
Route::get('/candidatos/{candidato}', [CandidatoController::class, 'show'])
    ->whereNumber('candidato')->name('candidatos.show');
Route::get('/candidatos/{candidato}/curriculo', [CurriculoController::class, 'show'])
    ->whereNumber('candidato')->name('candidatos.curriculo');

// US2 — importação
Route::get('/candidatos/importar', [ImportacaoCurriculoController::class, 'create'])->name('importacoes.create');
Route::post('/candidatos/importar', [ImportacaoCurriculoController::class, 'store'])->name('importacoes.store');
Route::get('/candidatos/importar/{importacao}', [ImportacaoCurriculoController::class, 'show'])
    ->whereUuid('importacao')->name('importacoes.show');
Route::post('/candidatos/importar/{importacao}/confirmar', [ConfirmacaoImportacaoController::class, 'store'])
    ->whereUuid('importacao')->name('importacoes.confirmar');
Route::post('/competencias', [CompetenciaController::class, 'store'])->name('competencias.store');
