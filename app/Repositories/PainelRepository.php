<?php

namespace App\Repositories;

use App\Enums\StatusVaga;
use App\Models\Vaga;
use App\Repositories\Interfaces\PainelRepositoryInterface;

class PainelRepository implements PainelRepositoryInterface
{
    public function contarVagasAbertas(): int
    {
        return Vaga::query()->where('status', StatusVaga::Aberta->value)->count();
    }
}
