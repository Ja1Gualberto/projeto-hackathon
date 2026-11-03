<?php

namespace App\Services;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class CandidatoService
{
    public function __construct(
        private CandidatoRepositoryInterface $candidatos,
        private CompetenciaRepositoryInterface $competencias,
    ) {}

    /** @return LengthAwarePaginator<int, Candidato> */
    public function buscar(FiltroCandidatosDTO $filtro): LengthAwarePaginator
    {
        $equivalencias = [];

        foreach ($filtro->competencias as $id) {
            $equivalencias[$id] = $this->competencias->idsQueAtendem($id);
        }

        return $this->candidatos->buscar($filtro->comCompetenciasEquivalentes($equivalencias), $filtro->porPagina);
    }

    /** @return list<int> */
    public function buscarIds(FiltroCandidatosDTO $filtro): array
    {
        return $this->candidatos->buscarIds($filtro);
    }

    public function detalhar(Candidato $candidato): Candidato
    {
        return $this->candidatos->detalhar($candidato);
    }

    public function alterarStatus(Candidato $candidato, StatusCandidato $status): Candidato
    {
        return $this->candidatos->atualizar($candidato, ['status' => $status]);
    }

    public function contarRetencaoVencida(): int
    {
        return $this->candidatos->contarRetencaoVencida();
    }

    /** @return array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>} */
    public function catalogoCompetencias(): array
    {
        return $this->competencias->todasAgrupadasPorCategoria();
    }

    /** @return array{arquivo: string, nomeOriginal: ?string}|null */
    public function obterCurriculo(Candidato $candidato): ?array
    {
        if ($candidato->curriculo_arquivo === null || ! Storage::disk('curriculos')->exists($candidato->curriculo_arquivo)) {
            return null;
        }

        return [
            'arquivo' => $candidato->curriculo_arquivo,
            'nomeOriginal' => $candidato->curriculo_nome_original,
        ];
    }

    public function contarAtivos(): int
    {
        return $this->candidatos->contarAtivos();
    }

    /** @return Collection<int, Competencia> */
    public function topCompetencias(int $limite = 8): Collection
    {
        return $this->candidatos->topCompetencias($limite);
    }

    public function encontrarPorEmailOuCpf(?string $email, ?string $cpf): ?Candidato
    {
        return $this->candidatos->encontrarPorEmailOuCpf($email, $cpf);
    }
}
