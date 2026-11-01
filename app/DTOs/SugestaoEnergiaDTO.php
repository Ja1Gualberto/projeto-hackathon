<?php

namespace App\DTOs;

final readonly class SugestaoEnergiaDTO
{
    public function __construct(
        public int $dinamicoAnalitico,
        public int $individualEquipe,
        public int $execucaoPlanejamento,
        public string $justificativa,
    ) {}

    /** @return array{dinamico_analitico: int, individual_equipe: int, execucao_planejamento: int} */
    public function eixos(): array
    {
        return [
            'dinamico_analitico' => $this->dinamicoAnalitico,
            'individual_equipe' => $this->individualEquipe,
            'execucao_planejamento' => $this->execucaoPlanejamento,
        ];
    }
}
