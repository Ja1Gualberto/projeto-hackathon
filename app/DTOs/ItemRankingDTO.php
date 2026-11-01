<?php

namespace App\DTOs;

use App\Enums\Area;
use App\Enums\EtapaSelecao;
use App\Enums\TipoCandidato;

/**
 * Um card do quadro da vaga. Não guarda o model `Candidato` nem o id, para a view da triagem
 * não ter como exibir identidade ou link para o perfil do banco (contracts/visibilidade-por-contexto.md).
 */
final readonly class ItemRankingDTO
{
    /**
     * @param  ?string  $nome  Nulo, exceto quando a etapa revela a identidade nesta vaga (US4).
     * @param  ?int  $proximidadeEnergia  Preenchida na US7.
     */
    public function __construct(
        public string $codigo,
        public TipoCandidato $tipo,
        public Area $area,
        public bool $experienciaNaArea,
        public ResultadoAderenciaDTO $resultado,
        public EtapaSelecao $etapa = EtapaSelecao::Triagem,
        public ?string $nome = null,
        public ?int $proximidadeEnergia = null,
    ) {}
}
