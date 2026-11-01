<?php

namespace App\Services\Aderencia;

use App\DTOs\ItemRankingDTO;
use App\DTOs\ResultadoAderenciaDTO;
use App\Enums\EtapaSelecao;
use App\Models\Candidato;
use App\Models\Vaga;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * Ranking dos elegíveis de uma vaga, calculado na leitura: qualquer mudança na vaga ou no perfil
 * aparece na próxima abertura do quadro (FR-020), sem score armazenado.
 */
class RankingVagaService
{
    public function __construct(
        private VagaRepositoryInterface $vagas,
        private CalculadoraAderencia $calculadora,
    ) {}

    /**
     * @return Collection<int, ItemRankingDTO>
     */
    public function ranquear(Vaga $vaga): Collection
    {
        $requisitos = $this->vagas->requisitos($vaga);

        return $this->vagas->candidatosElegiveis($vaga)
            ->map(fn (Candidato $candidato): array => [
                'candidato' => $candidato,
                'resultado' => $this->calculadora->calcular($requisitos, $candidato->competencias),
            ])
            ->sort($this->comparar(...))
            ->map(fn (array $linha): ItemRankingDTO => new ItemRankingDTO(
                codigo: $linha['candidato']->codigo,
                tipo: $linha['candidato']->tipo,
                area: $linha['candidato']->area,
                experienciaNaArea: $linha['candidato']->experiencia_na_area,
                resultado: $linha['resultado'],
                etapa: EtapaSelecao::Triagem,
            ))
            ->values();
    }

    /**
     * Percentual ↓, peso essencial atendido ↓, data de cadastro ↑ e id ↑ (research R7).
     *
     * @param  array{candidato: Candidato, resultado: ResultadoAderenciaDTO}  $a
     * @param  array{candidato: Candidato, resultado: ResultadoAderenciaDTO}  $b
     */
    private function comparar(array $a, array $b): int
    {
        return [$b['resultado']->percentual, $b['resultado']->pesoEssencialAtendido, $this->cadastradoEm($a['candidato']), $a['candidato']->id]
            <=> [$a['resultado']->percentual, $a['resultado']->pesoEssencialAtendido, $this->cadastradoEm($b['candidato']), $b['candidato']->id];
    }

    private function cadastradoEm(Candidato $candidato): int
    {
        return (int) $candidato->created_at?->getTimestamp();
    }
}
