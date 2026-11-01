<?php

namespace App\Services\Energia;

use App\DTOs\SugestaoEnergiaDTO;

class AnalisadorEnergiaIndisponivel implements AnalisadorEnergia
{
    public function analisar(string $textoAnonimizado): ?SugestaoEnergiaDTO
    {
        return null;
    }
}
