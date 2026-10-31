<?php

namespace App\Enums;

enum CategoriaCompetencia: string
{
    case Hard = 'hard';
    case Soft = 'soft';

    public function rotulo(): string
    {
        return match ($this) {
            self::Hard => 'Técnica (hard skill)',
            self::Soft => 'Comportamental (soft skill)',
        };
    }
}
