<?php

namespace App\Enums;

enum TipoCandidato: string
{
    case Servidor = 'servidor';
    case Estagiario = 'estagiario';

    public function rotulo(): string
    {
        return match ($this) {
            self::Servidor => 'Servidor',
            self::Estagiario => 'Estagiário',
        };
    }

    /**
     * Classes Tailwind do token visual do tipo (cor de fundo, texto e contorno).
     */
    public function cor(): string
    {
        return match ($this) {
            self::Servidor => 'bg-servidor-100 text-servidor-800 ring-servidor-300',
            self::Estagiario => 'bg-estagiario-100 text-estagiario-800 ring-estagiario-300',
        };
    }

    /**
     * Ícone que acompanha a cor, para a diferença não depender só da cor.
     */
    public function icone(): string
    {
        return match ($this) {
            self::Servidor => 'vaga',
            self::Estagiario => 'formacao',
        };
    }
}
