<?php

namespace App\Repositories\Interfaces;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\MotivoExclusao;
use App\Enums\OrigemCompetencia;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\PerfilEnergia;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CandidatoRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Candidato> */
    public function buscar(FiltroCandidatosDTO $filtro, int $porPagina = 12): LengthAwarePaginator;

    /** @return list<int> */
    public function buscarIds(FiltroCandidatosDTO $filtro): array;

    public function detalhar(Candidato $candidato): Candidato;

    /** @param array<string, mixed> $atributos */
    public function salvarPerfilEnergia(Candidato $candidato, array $atributos): PerfilEnergia;

    /** @return Collection<int, Candidato> */
    public function candidatosParaAnaliseEnergia(bool $todos): Collection;

    public function contarAtivos(): int;

    public function contarRetencaoVencida(): int;

    public function excluir(Candidato $candidato, MotivoExclusao $motivo, User $responsavel): void;

    /** @return Collection<int, Competencia> */
    public function topCompetencias(int $limite = 8): Collection;

    public function encontrarPorEmailOuCpf(?string $email, ?string $cpf): ?Candidato;

    public function encontrar(int $id): ?Candidato;

    /** @param array<string, mixed> $atributos */
    public function criar(array $atributos): Candidato;

    /** @param array<string, mixed> $atributos */
    public function atualizar(Candidato $candidato, array $atributos): Candidato;

    /** @return array<int, OrigemCompetencia> Origem atual de cada competência do candidato, por id. */
    public function origensDasCompetencias(Candidato $candidato): array;

    /**
     * Deixa o candidato exatamente com as competências informadas, cada uma com a sua origem.
     *
     * @param  array<int, OrigemCompetencia>  $origens
     */
    public function sincronizarCompetencias(Candidato $candidato, array $origens): void;
}
