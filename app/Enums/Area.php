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
}
