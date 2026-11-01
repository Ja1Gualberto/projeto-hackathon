<?php

namespace App\Services\Energia;

class ProximidadeEnergia
{
    /**
     * @param  array{0: int, 1: int, 2: int}  $vaga
     * @param  array{0: int, 1: int, 2: int}  $candidato
     */
    public function calcular(array $vaga, array $candidato): int
    {
        $distancia = abs($vaga[0] - $candidato[0]) + abs($vaga[1] - $candidato[1]) + abs($vaga[2] - $candidato[2]);

        return (int) round((1 - $distancia / 12) * 100);
    }
}
