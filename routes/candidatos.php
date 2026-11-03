<?php

use App\Http\Controllers\AnotacaoController;
use App\Http\Controllers\CandidatoController;
use App\Http\Controllers\CandidatoStatusController;
use App\Http\Controllers\CompetenciaController;
use App\Http\Controllers\ConfirmacaoImportacaoController;
use App\Http\Controllers\CurriculoController;
use App\Http\Controllers\ImportacaoCurriculoController;
use App\Http\Controllers\LoteImportacaoController;
use App\Http\Controllers\PerfilEnergiaController;
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
Route::delete('/candidatos/importar/{importacao}', [ImportacaoCurriculoController::class, 'destroy'])
    ->whereUuid('importacao')->name('importacoes.destroy');
Route::get('/candidatos/importar/{importacao}/pdf', [ImportacaoCurriculoController::class, 'pdf'])
    ->whereUuid('importacao')->name('importacoes.pdf');
Route::post('/candidatos/importar/{importacao}/confirmar', [ConfirmacaoImportacaoController::class, 'store'])
    ->whereUuid('importacao')->name('importacoes.confirmar');
Route::get('/candidatos/importar/lotes/{lote}', [LoteImportacaoController::class, 'show'])
    ->whereUuid('lote')->name('importacoes.lotes.show');
Route::post('/candidatos/importar/lotes/{lote}/cadastrar', [LoteImportacaoController::class, 'cadastrar'])
    ->whereUuid('lote')->name('importacoes.lotes.cadastrar');
Route::post('/competencias', [CompetenciaController::class, 'store'])->name('competencias.store');

// US5 — anotações
Route::post('/candidatos/{candidato}/anotacoes', [AnotacaoController::class, 'store'])
    ->whereNumber('candidato')->name('candidatos.anotacoes.store');

// US6 — LGPD
Route::get('/candidatos/{candidato}/editar', [CandidatoController::class, 'edit'])
    ->whereNumber('candidato')->name('candidatos.edit');
Route::put('/candidatos/{candidato}', [CandidatoController::class, 'update'])
    ->whereNumber('candidato')->name('candidatos.update');
Route::patch('/candidatos/{candidato}/status', [CandidatoStatusController::class, 'update'])
    ->whereNumber('candidato')->name('candidatos.status');
Route::delete('/candidatos/{candidato}', [CandidatoController::class, 'destroy'])
    ->whereNumber('candidato')->name('candidatos.destroy');

// US7 — energia
Route::post('/candidatos/{candidato}/energia', [PerfilEnergiaController::class, 'store'])
    ->whereNumber('candidato')->name('candidatos.energia.analisar');
Route::put('/candidatos/{candidato}/energia', [PerfilEnergiaController::class, 'update'])
    ->whereNumber('candidato')->name('candidatos.energia.update');
