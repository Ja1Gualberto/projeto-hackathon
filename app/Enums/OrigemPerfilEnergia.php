<?php

namespace App\Enums;

enum OrigemPerfilEnergia: string
{
    case Ia = 'ia';
    case Humano = 'humano';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ia => 'Sugerido pela IA',
            self::Humano => 'Revisado por humano',
        };
    }
}
