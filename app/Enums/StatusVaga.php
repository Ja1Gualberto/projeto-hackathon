<?php

namespace App\Enums;

enum StatusVaga: string
{
    case Aberta = 'aberta';
    case Encerrada = 'encerrada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Encerrada => 'Encerrada',
        };
    }
}
