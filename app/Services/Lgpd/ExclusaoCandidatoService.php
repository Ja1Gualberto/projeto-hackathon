<?php

namespace App\Services\Lgpd;

use App\Enums\MotivoExclusao;
use App\Models\Candidato;
use App\Models\User;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExclusaoCandidatoService
{
    public function __construct(private CandidatoRepositoryInterface $candidatos) {}

    public function excluir(Candidato $candidato, MotivoExclusao $motivo, User $responsavel): void
    {
        $arquivo = $candidato->curriculo_arquivo;

        DB::transaction(function () use ($candidato, $motivo, $responsavel, $arquivo): void {
            $this->candidatos->excluir($candidato, $motivo, $responsavel);

            DB::afterCommit(function () use ($arquivo): void {
                if ($arquivo !== null && ! Storage::disk('curriculos')->delete($arquivo)) {
                    throw new RuntimeException('Não foi possível remover o currículo. Confira a exclusão antes de tentar novamente.');
                }
            });
        });
    }
}
