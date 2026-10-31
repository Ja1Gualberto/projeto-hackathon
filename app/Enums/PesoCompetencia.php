<?php

namespace App\Enums;

enum PesoCompetencia: int
{
    case Desejavel = 1;
    case Importante = 2;
    case Essencial = 3;

    public function rotulo(): string
    {
        return match ($this) {
            self::Desejavel => 'Desejável',
            self::Importante => 'Importante',
            self::Essencial => 'Essencial',
        };
    }
}
