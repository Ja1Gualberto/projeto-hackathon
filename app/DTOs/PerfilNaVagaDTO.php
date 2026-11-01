<?php

namespace App\DTOs;

use App\Enums\Area;
use App\Enums\EtapaSelecao;
use App\Enums\TipoCandidato;
use App\Models\Anotacao;
use App\Models\Competencia;
use App\Models\MovimentacaoEtapa;
use Illuminate\Support\Collection;

/**
 * Perfil do candidato no contexto de uma vaga (contracts/visibilidade-por-contexto.md). A view
 * recebe só este DTO, nunca o model `Candidato`: em triagem, identidade, contatos, idade, links,
 * anotações e PDF chegam nulos/falsos, e os textos livres já vêm limpos.
 */
final readonly class PerfilNaVagaDTO
{
    /**
     * @param  list<string>  $formacoes
     * @param  list<string>  $experiencias
     * @param  list<string>  $projetos
     * @param  Collection<int, Competencia>  $competencias  Com `pivot->origem`.
     * @param  Collection<int, MovimentacaoEtapa>  $movimentacoes  Histórico desta vaga, com o usuário.
     * @param  bool  $podeMover  Vaga aberta e candidato ativo e elegível.
     * @param  list<string>|null  $links
     * @param  Collection<int, Anotacao>|null  $anotacoes  Todas as anotações, de qualquer vaga, quando revelado.
     */
    public function __construct(
        public string $codigo,
        public bool $identidadeRevelada,
        public EtapaSelecao $etapa,
        public TipoCandidato $tipo,
        public Area $area,
        public bool $experienciaNaArea,
        public array $formacoes,
        public array $experiencias,
        public array $projetos,
        public Collection $competencias,
        public ResultadoAderenciaDTO $resultado,
        public int $quantidadeLinks,
        public Collection $movimentacoes,
        public bool $podeMover = false,
        public bool $desativado = false,
        public ?string $nome = null,
        public ?string $email = null,
        public ?string $telefone = null,
        public ?int $idade = null,
        public ?array $links = null,
        public ?Collection $anotacoes = null,
        public bool $temCurriculo = false,
    ) {}
}
