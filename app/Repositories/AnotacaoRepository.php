<?php

namespace App\Repositories;

use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\AnotacaoRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AnotacaoRepository implements AnotacaoRepositoryInterface
{
    public function criar(Candidato $candidato, ?Vaga $vaga, User $autor, string $texto, array $competenciasObservadas): Anotacao
    {
        $anotacao = Anotacao::query()->create([
            'candidato_id' => $candidato->id,
            'vaga_id' => $vaga?->id,
            'user_id' => $autor->id,
            'texto' => $texto,
        ]);
        $anotacao->competenciasObservadas()->sync($competenciasObservadas);

        return $anotacao;
    }

    public function historico(Candidato $candidato): Collection
    {
        return $candidato->anotacoes()
            ->with([
                'autor',
                'vaga',
                'competenciasObservadas' => fn (BelongsToMany $query): BelongsToMany => $query->orderBy('nome'),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
