<?php

namespace App\Enums;

enum StatusCandidato: string
{
    case Ativo = 'ativo';
    case Desativado = 'desativado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Desativado => 'Desativado',
        };
    }
}
