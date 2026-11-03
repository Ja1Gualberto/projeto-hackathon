<?php

namespace App\Repositories;

use App\Enums\EtapaSelecao;
use App\Enums\StatusVaga;
use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VagaRepository implements VagaRepositoryInterface
{
    public function listar(): Collection
    {
        return Vaga::query()
            ->withCount([
                'competencias',
                'participacoes as elegiveis_count' => fn (Builder $query) => $query->whereHas('candidato', fn (Builder $candidatos) => $candidatos->ativos()),
                'participacoes as entrevista_count' => fn (Builder $query) => $query->where('etapa', EtapaSelecao::Entrevista->value),
                'participacoes as aprovado_count' => fn (Builder $query) => $query->where('etapa', EtapaSelecao::Aprovado->value),
            ])
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StatusVaga::Aberta->value])
            ->latest()
            ->orderByDesc('id')
            ->get();
    }

    public function criar(array $dados, array $competenciasComPeso, User $criador): Vaga
    {
        $vaga = Vaga::query()->create([...$dados, 'criada_por' => $criador->id]);
        $vaga->competencias()->sync($this->pivos($competenciasComPeso));

        return $vaga;
    }

    public function atualizar(Vaga $vaga, array $dados, array $competenciasComPeso): Vaga
    {
        $vaga->fill($dados)->save();
        $vaga->competencias()->sync($this->pivos($competenciasComPeso));

        return $vaga;
    }

    public function encerrar(Vaga $vaga): void
    {
        if ($vaga->status === StatusVaga::Encerrada) {
            return;
        }

        $vaga->forceFill(['status' => StatusVaga::Encerrada, 'encerrada_em' => now()])->save();
    }

    public function listarAbertas(): Collection
    {
        return Vaga::query()->where('status', StatusVaga::Aberta)->orderBy('titulo')->orderBy('id')->get(['id', 'titulo']);
    }

    public function encontrar(int $id, bool $bloquear = false): ?Vaga
    {
        return Vaga::query()->when($bloquear, fn (Builder $query) => $query->lockForUpdate())->find($id);
    }

    public function requisitos(Vaga $vaga): Collection
    {
        return $vaga->competencias()->orderByPivot('peso', 'desc')->orderBy('nome')->get();
    }

    public function candidatosElegiveis(Vaga $vaga): Collection
    {
        return Candidato::query()
            ->elegiveisPara($vaga)
            ->with(['competencias', 'perfilEnergia'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int, int>  $competenciasComPeso
     * @return array<int, array{peso: int}>
     */
    private function pivos(array $competenciasComPeso): array
    {
        return array_map(fn (int $peso): array => ['peso' => $peso], $competenciasComPeso);
    }
}
