<?php

namespace App\Services\Energia;

use App\DTOs\SugestaoEnergiaDTO;

interface AnalisadorEnergia
{
    public function analisar(string $textoAnonimizado): ?SugestaoEnergiaDTO;
}
