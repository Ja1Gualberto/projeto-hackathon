<?php

namespace App\Services\Aderencia;

use App\DTOs\ResultadoAderenciaDTO;
use App\Enums\OrigemCompetencia;
use App\Enums\PesoCompetencia;
use App\Models\Competencia;
use Illuminate\Support\Collection;

/**
 * Fórmula de aderência (FR-017, research R7), sem acesso a banco:
 * round(Σ pesos atendidos ÷ Σ pesos da vaga × 100), com arredondamento half up.
 * Toda origem conta; experiência, projetos e energia não alteram o percentual (FR-019).
 */
class CalculadoraAderencia
{
    /**
     * @param  Collection<int, Competencia>  $requisitos  Competências da vaga, com `pivot->peso`.
     * @param  Collection<int, Competencia>  $competenciasDoCandidato  Competências do candidato, com `pivot->origem`.
     */
    public function calcular(Collection $requisitos, Collection $competenciasDoCandidato): ResultadoAderenciaDTO
    {
        $atendidas = [];
        $naoIdentificadas = [];
        $pesoAtendido = 0;
        $pesoTotal = 0;
        $pesoEssencialAtendido = 0;

        foreach ($this->ordenarPorPeso($requisitos) as $requisito) {
            $peso = (int) $requisito->pivot->peso;
            $pesoTotal += $peso;
            $origem = $this->origemDoAtendimento($requisito, $competenciasDoCandidato);

            if ($origem === null) {
                $naoIdentificadas[] = ['nome' => $requisito->nome, 'peso' => $peso];

                continue;
            }

            $atendidas[] = ['nome' => $requisito->nome, 'peso' => $peso, 'origem' => $origem];
            $pesoAtendido += $peso;

            if ($peso === PesoCompetencia::Essencial->value) {
                $pesoEssencialAtendido += $peso;
            }
        }

        return new ResultadoAderenciaDTO(
            percentual: $this->percentual($pesoAtendido, $pesoTotal),
            pesoAtendido: $pesoAtendido,
            pesoTotal: $pesoTotal,
            pesoEssencialAtendido: $pesoEssencialAtendido,
            atendidas: $atendidas,
            naoIdentificadas: $naoIdentificadas,
        );
    }

    /**
     * Aritmética inteira para o half up exato (12,5 → 13), sem erro de ponto flutuante.
     */
    private function percentual(int $pesoAtendido, int $pesoTotal): int
    {
        if ($pesoTotal === 0) {
            return 0;
        }

        return intdiv(200 * $pesoAtendido + $pesoTotal, 2 * $pesoTotal);
    }

    /**
     * Mesmo id, ou, para idiomas, uma competência do mesmo grupo com nível maior ou igual.
     * Devolve a origem da competência que atende (a exata tem preferência), ou null.
     *
     * @param  Collection<int, Competencia>  $competenciasDoCandidato
     */
    private function origemDoAtendimento(Competencia $requisito, Collection $competenciasDoCandidato): ?OrigemCompetencia
    {
        $atende = $competenciasDoCandidato->first(fn (Competencia $competencia): bool => $competencia->id === $requisito->id)
            ?? ($requisito->ehIdioma()
                ? $competenciasDoCandidato
                    ->filter(fn (Competencia $competencia): bool => $competencia->ehIdioma()
                        && $competencia->grupo === $requisito->grupo
                        && $competencia->nivel >= $requisito->nivel)
                    ->sortByDesc(fn (Competencia $competencia): int => $this->origem($competencia)->prioridade())
                    ->first()
                : null);

        return $atende === null ? null : $this->origem($atende);
    }

    private function origem(Competencia $competencia): OrigemCompetencia
    {
        $origem = $competencia->pivot->origem;

        return $origem instanceof OrigemCompetencia ? $origem : OrigemCompetencia::from($origem);
    }

    /**
     * Maior peso primeiro e, no empate, ordem alfabética: a explicação cita antes o que mais pesa.
     *
     * @param  Collection<int, Competencia>  $requisitos
     * @return Collection<int, Competencia>
     */
    private function ordenarPorPeso(Collection $requisitos): Collection
    {
        return $requisitos->sort(fn (Competencia $a, Competencia $b): int => [(int) $b->pivot->peso, $a->nome] <=> [(int) $a->pivot->peso, $b->nome]);
    }
}
