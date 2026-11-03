<?php

namespace App\Repositories;

use App\Enums\EtapaSelecao;
use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\Participacao;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\ParticipacaoRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ParticipacaoRepository implements ParticipacaoRepositoryInterface
{
    public function etapasPorCandidato(Vaga $vaga): array
    {
        return Participacao::query()
            ->where('vaga_id', $vaga->id)
            ->get(['candidato_id', 'etapa'])
            ->mapWithKeys(fn (Participacao $participacao): array => [$participacao->candidato_id => $participacao->etapa])
            ->all();
    }

    public function encontrar(Vaga $vaga, Candidato $candidato, bool $bloquear = false): ?Participacao
    {
        return Participacao::query()
            ->where('vaga_id', $vaga->id)
            ->where('candidato_id', $candidato->id)
            ->when($bloquear, fn (Builder $query) => $query->lockForUpdate())
            ->first();
    }

    public function moverPara(Vaga $vaga, Candidato $candidato, EtapaSelecao $etapa, User $usuario): Participacao
    {
        return DB::transaction(function () use ($vaga, $candidato, $etapa, $usuario): Participacao {
            $participacao = Participacao::query()
                ->where('vaga_id', $vaga->id)
                ->where('candidato_id', $candidato->id)
                ->lockForUpdate()
                ->first()
                ?? new Participacao(['vaga_id' => $vaga->id, 'candidato_id' => $candidato->id, 'etapa' => EtapaSelecao::Triagem]);

            $de = $participacao->etapa;

            if ($etapa === EtapaSelecao::Triagem && $de !== EtapaSelecao::Triagem) {
                throw ValidationException::withMessages(['etapa' => 'Não é possível voltar à triagem após iniciar a entrevista.']);
            }

            if ($de === $etapa) {
                return $participacao;
            }

            $participacao->etapa = $etapa;
            $participacao->save();
            $participacao->movimentacoes()->create(['user_id' => $usuario->id, 'de' => $de, 'para' => $etapa]);

            return $participacao;
        });
    }

    public function historico(Vaga $vaga, Candidato $candidato): Collection
    {
        return $this->encontrar($vaga, $candidato)?->movimentacoes()
            ->with('usuario')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ?? new Collection;
    }

    public function adicionarATriagem(Vaga $vaga, array $candidatoIds): void
    {
        DB::transaction(function () use ($vaga, $candidatoIds): void {
            $vaga = Vaga::query()->lockForUpdate()->findOrFail($vaga->id);

            if (! $vaga->estaAberta()) {
                throw ValidationException::withMessages(['vaga' => 'Vaga encerrada — somente consulta. Não é possível adicionar candidatos.']);
            }

            $candidatos = Candidato::query()->whereIn('id', $candidatoIds)->lockForUpdate()->get();

            if ($candidatos->count() !== count(array_unique($candidatoIds)) || $candidatos->contains(fn (Candidato $candidato): bool => $candidato->status !== StatusCandidato::Ativo)) {
                throw ValidationException::withMessages(['candidatos' => 'Selecione apenas candidatos ativos do banco de talentos.']);
            }

            foreach ($candidatos as $candidato) {
                Participacao::query()->firstOrCreate(
                    ['vaga_id' => $vaga->id, 'candidato_id' => $candidato->id],
                    ['etapa' => EtapaSelecao::Triagem],
                );
            }
        });
    }

    public function candidatosRevelados(Vaga $vaga): Collection
    {
        return Candidato::query()
            ->whereHas('participacoes', fn (Builder $query) => $query
                ->where('vaga_id', $vaga->id)
                ->where('etapa', '!=', EtapaSelecao::Triagem->value))
            ->with(['competencias', 'perfilEnergia'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }
}
