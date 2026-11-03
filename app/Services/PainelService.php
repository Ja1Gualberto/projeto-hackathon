<?php

namespace App\Services;

use App\Repositories\Interfaces\PainelRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PainelService
{
    public function __construct(
        private CandidatoService $candidatos,
        private PainelRepositoryInterface $painel,
    ) {}

    /** @return array{totalTalentos: int, vagasAbertas: int, topCompetencias: Collection, retencaoPendente: int} */
    public function resumo(): array
    {
        return [
            'totalTalentos' => $this->candidatos->contarAtivos(),
            'vagasAbertas' => $this->painel->contarVagasAbertas(),
            'topCompetencias' => $this->candidatos->topCompetencias(),
            'retencaoPendente' => $this->candidatos->contarRetencaoVencida(),
        ];
    }
}
