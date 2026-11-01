<?php

namespace App\DTOs;

use App\Enums\OrigemCompetencia;

/**
 * Aderência de um candidato a uma vaga e a explicação exibida no card (FR-017/FR-018, research R7).
 * "Não identificada" significa que a competência não foi encontrada no perfil, nunca que o
 * candidato não a possui.
 */
final readonly class ResultadoAderenciaDTO
{
    /**
     * @param  list<array{nome: string, peso: int, origem: OrigemCompetencia}>  $atendidas
     * @param  list<array{nome: string, peso: int}>  $naoIdentificadas
     */
    public function __construct(
        public int $percentual,
        public int $pesoAtendido,
        public int $pesoTotal,
        public int $pesoEssencialAtendido,
        public array $atendidas,
        public array $naoIdentificadas,
    ) {}

    /**
     * Ex.: "80% — tem 4 de 5 competências; não identificado: Git (peso 2)".
     */
    public function explicacao(): string
    {
        $total = count($this->atendidas) + count($this->naoIdentificadas);

        if ($this->atendidas === []) {
            return "{$this->percentual}% — nenhuma competência solicitada foi identificada no currículo";
        }

        if ($this->naoIdentificadas === []) {
            return $total === 1
                ? "{$this->percentual}% — tem a competência solicitada"
                : "{$this->percentual}% — tem todas as {$total} competências";
        }

        $faltantes = implode(', ', array_map(
            fn (array $competencia): string => "{$competencia['nome']} (peso {$competencia['peso']})",
            $this->naoIdentificadas,
        ));

        return sprintf('%d%% — tem %d de %d competências; não identificado: %s',
            $this->percentual, count($this->atendidas), $total, $faltantes);
    }
}
