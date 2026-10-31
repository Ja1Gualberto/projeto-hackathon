<?php

namespace App\Enums;

enum MeioConsentimento: string
{
    case Email = 'email';
    case Formulario = 'formulario';
    case DeclaracaoCiee = 'declaracao_ciee';
    case Presencial = 'presencial';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Formulario => 'Formulário',
            self::DeclaracaoCiee => 'Declaração do CIEE',
            self::Presencial => 'Presencial',
            self::Outro => 'Outro',
        };
    }
}
