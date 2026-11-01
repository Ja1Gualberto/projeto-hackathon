<?php

namespace App\DTOs;

/**
 * Dados lidos automaticamente de um currículo, antes da revisão humana.
 * Serializável em array para guardar na sessão da importação (contracts/extracao-curriculo.md).
 *
 * @phpstan-type CompetenciaExtraida array{id: int, nome: string, categoria: string, trecho: string}
 * @phpstan-type DadosExtraidosArray array{
 *     nome: ?string,
 *     email: ?string,
 *     telefone: ?string,
 *     cpf: ?string,
 *     data_nascimento: ?string,
 *     formacoes: list<string>,
 *     experiencias: list<string>,
 *     projetos: list<string>,
 *     links: list<string>,
 *     competencias: list<CompetenciaExtraida>,
 *     sugestoes_competencias: list<string>,
 *     area_sugerida: ?string,
 *     experiencia_na_area: bool,
 * }
 */
final readonly class DadosExtraidosDTO
{
    /**
     * @param  list<string>  $formacoes
     * @param  list<string>  $experiencias
     * @param  list<string>  $projetos
     * @param  list<string>  $links
     * @param  list<array{id: int, nome: string, categoria: string, trecho: string}>  $competencias  Sempre de origem `curriculo`.
     * @param  list<string>  $sugestoes_competencias  Termos fora do catálogo; nunca cadastrados sozinhos.
     * @param  bool  $experiencia_na_area  `false` significa "não informado", nunca "não possui".
     */
    public function __construct(
        public ?string $nome = null,
        public ?string $email = null,
        public ?string $telefone = null,
        public ?string $cpf = null,
        public ?string $data_nascimento = null,
        public array $formacoes = [],
        public array $experiencias = [],
        public array $projetos = [],
        public array $links = [],
        public array $competencias = [],
        public array $sugestoes_competencias = [],
        public ?string $area_sugerida = null,
        public bool $experiencia_na_area = false,
    ) {}

    public static function vazio(): self
    {
        return new self;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function fromArray(array $dados): self
    {
        return new self(
            nome: $dados['nome'] ?? null,
            email: $dados['email'] ?? null,
            telefone: $dados['telefone'] ?? null,
            cpf: $dados['cpf'] ?? null,
            data_nascimento: $dados['data_nascimento'] ?? null,
            formacoes: array_values($dados['formacoes'] ?? []),
            experiencias: array_values($dados['experiencias'] ?? []),
            projetos: array_values($dados['projetos'] ?? []),
            links: array_values($dados['links'] ?? []),
            competencias: array_values($dados['competencias'] ?? []),
            sugestoes_competencias: array_values($dados['sugestoes_competencias'] ?? []),
            area_sugerida: $dados['area_sugerida'] ?? null,
            experiencia_na_area: (bool) ($dados['experiencia_na_area'] ?? false),
        );
    }

    /**
     * @return DadosExtraidosArray
     */
    public function toArray(): array
    {
        return [
            'nome' => $this->nome,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'cpf' => $this->cpf,
            'data_nascimento' => $this->data_nascimento,
            'formacoes' => $this->formacoes,
            'experiencias' => $this->experiencias,
            'projetos' => $this->projetos,
            'links' => $this->links,
            'competencias' => $this->competencias,
            'sugestoes_competencias' => $this->sugestoes_competencias,
            'area_sugerida' => $this->area_sugerida,
            'experiencia_na_area' => $this->experiencia_na_area,
        ];
    }

    /**
     * @return list<int>
     */
    public function idsCompetencias(): array
    {
        return array_map(fn (array $competencia): int => $competencia['id'], $this->competencias);
    }

    /**
     * @return array<int, string> Trecho do currículo em que cada competência foi encontrada, por id.
     */
    public function trechosPorCompetencia(): array
    {
        return array_column($this->competencias, 'trecho', 'id');
    }
}
