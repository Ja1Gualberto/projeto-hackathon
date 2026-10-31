<?php

namespace App\Enums;

enum TipoCandidatoVaga: string
{
    case Servidor = 'servidor';
    case Estagiario = 'estagiario';
    case Ambos = 'ambos';

    public function rotulo(): string
    {
        return match ($this) {
            self::Servidor => 'Servidores',
            self::Estagiario => 'Estagiários',
            self::Ambos => 'Servidores e estagiários',
        };
    }

    public function aceita(TipoCandidato $tipo): bool
    {
        return $this === self::Ambos || $this->value === $tipo->value;
    }
}
