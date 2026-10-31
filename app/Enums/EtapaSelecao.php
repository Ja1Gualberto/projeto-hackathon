<?php

namespace App\Enums;

enum EtapaSelecao: string
{
    case Triagem = 'triagem';
    case Entrevista = 'entrevista';
    case Aprovado = 'aprovado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Triagem => 'Triagem',
            self::Entrevista => 'Entrevista',
            self::Aprovado => 'Aprovado',
        };
    }

    /**
     * A identidade do candidato só é revelada a partir da entrevista, e apenas na vaga em questão.
     */
    public function revelaIdentidade(): bool
    {
        return $this !== self::Triagem;
    }
}
