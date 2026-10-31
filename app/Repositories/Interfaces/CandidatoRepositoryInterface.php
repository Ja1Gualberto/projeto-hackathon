<?php

namespace App\Repositories\Interfaces;

use App\DTOs\FiltroCandidatosDTO;
use App\Models\Candidato;
use App\Models\Competencia;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface CandidatoRepositoryInterface
{
    /** @return LengthAwarePaginator<int, Candidato> */
    public function buscar(FiltroCandidatosDTO $filtro, int $porPagina = 12): LengthAwarePaginator;

    public function detalhar(Candidato $candidato): Candidato;

    /** @return array{servidores: int, estagiarios: int} */
    public function contarAtivosPorTipo(): array;

    /** @return Collection<int, Competencia> */
    public function topCompetencias(int $limite = 8): Collection;

    public function encontrarPorEmailOuCpf(?string $email, ?string $cpf): ?Candidato;
}
