<?php

namespace App\Enums;

enum Area: string
{
    case Desenvolvimento = 'desenvolvimento';
    case Infraestrutura = 'infraestrutura';
    case Suporte = 'suporte';
    case Dados = 'dados';
    case Produto = 'produto';
    case Seguranca = 'seguranca';
    case Design = 'design';

    public function rotulo(): string
    {
        return match ($this) {
            self::Desenvolvimento => 'Desenvolvimento',
            self::Infraestrutura => 'Infraestrutura e Redes',
            self::Suporte => 'Suporte Técnico',
            self::Dados => 'Dados e BI',
            self::Produto => 'Produto e Análise de Sistemas',
            self::Seguranca => 'Segurança da Informação',
            self::Design => 'Design e UX',
        };
    }

    /**
     * Classe Tailwind da cor que identifica a área na capa das vagas.
     */
    public function cor(): string
    {
        return match ($this) {
            self::Desenvolvimento => 'bg-blue-600',
            self::Infraestrutura => 'bg-yellow-400',
            self::Suporte => 'bg-emerald-600',
            self::Dados => 'bg-violet-600',
            self::Produto => 'bg-orange-500',
            self::Seguranca => 'bg-red-600',
            self::Design => 'bg-pink-500',
        };
    }
}
