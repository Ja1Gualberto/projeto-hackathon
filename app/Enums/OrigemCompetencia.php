<?php

namespace App\Enums;

enum OrigemCompetencia: string
{
    case Curriculo = 'curriculo';
    case Revisao = 'revisao';
    case Entrevista = 'entrevista';

    public function rotulo(): string
    {
        return match ($this) {
            self::Curriculo => 'Declarada no currículo',
            self::Revisao => 'Adicionada na revisão',
            self::Entrevista => 'Observada em entrevista',
        };
    }

    /**
     * Rótulo curto usado nas tags de competência.
     */
    public function rotuloCurto(): string
    {
        return match ($this) {
            self::Curriculo => 'declarada',
            self::Revisao => 'revisada',
            self::Entrevista => 'observada',
        };
    }

    /**
     * Precedência ao reatribuir a origem: entrevista > revisão > currículo.
     * Uma origem de prioridade maior nunca é rebaixada.
     */
    public function prioridade(): int
    {
        return match ($this) {
            self::Curriculo => 1,
            self::Revisao => 2,
            self::Entrevista => 3,
        };
    }
}
