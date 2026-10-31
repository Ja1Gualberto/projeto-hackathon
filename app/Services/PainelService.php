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

    /** @return array{totalTalentos: int, servidores: int, estagiarios: int, vagasAbertas: int, topCompetencias: Collection, retencaoPendente: int} */
    public function resumo(): array
    {
        $totais = $this->candidatos->contarAtivosPorTipo();

        return [
            'totalTalentos' => array_sum($totais),
            ...$totais,
            'vagasAbertas' => $this->painel->contarVagasAbertas(),
            'topCompetencias' => $this->candidatos->topCompetencias(),
            'retencaoPendente' => 0,
        ];
    }
}
