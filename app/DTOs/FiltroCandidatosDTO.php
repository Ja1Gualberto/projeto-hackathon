<?php

namespace App\DTOs;

use App\Enums\Area;

final readonly class FiltroCandidatosDTO
{
    /**
     * @param  list<int>  $competencias
     * @param  array<int, list<int>>  $competenciasEquivalentes  Grupos resolvidos pelo service, sem perder os ids selecionados.
     */
    public function __construct(
        public array $competencias = [],
        public ?int $vagaId = null,
        public ?Area $area = null,
        public bool $somenteComExperiencia = false,
        public ?string $busca = null,
        public string $situacao = 'ativos',
        public array $competenciasEquivalentes = [],
        public ?int $excluirVagaId = null,
    ) {}

    /** @param array{competencias?: list<int|string>|null, vaga_id?: int|string|null, area?: string|null, experiencia?: bool|int|string|null, busca?: string|null, situacao?: string|null} $validados */
    public static function fromArray(array $validados): self
    {
        return new self(
            competencias: array_values(array_unique(array_map('intval', $validados['competencias'] ?? []))),
            vagaId: isset($validados['vaga_id']) ? (int) $validados['vaga_id'] : null,
            area: isset($validados['area']) ? Area::from($validados['area']) : null,
            somenteComExperiencia: (bool) ($validados['experiencia'] ?? false),
            busca: filled($validados['busca'] ?? null) ? trim($validados['busca']) : null,
            situacao: $validados['situacao'] ?? 'ativos',
        );
    }

    /** @param array<int, list<int>> $equivalencias */
    public function comCompetenciasEquivalentes(array $equivalencias): self
    {
        return new self($this->competencias, $this->vagaId, $this->area, $this->somenteComExperiencia,
            $this->busca, $this->situacao, $equivalencias, $this->excluirVagaId);
    }
}
