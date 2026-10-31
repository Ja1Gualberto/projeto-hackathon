<?php

namespace App\Repositories;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\TipoCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CandidatoRepository implements CandidatoRepositoryInterface
{
    public function __construct(private CompetenciaRepositoryInterface $competencias) {}

    public function buscar(FiltroCandidatosDTO $filtro, int $porPagina = 12): LengthAwarePaginator
    {
        $consulta = Candidato::query()->ativos()->with([
            'competencias' => fn (BelongsToMany $query): BelongsToMany => $query->orderBy('nome'),
        ]);

        foreach ($filtro->competencias as $id) {
            $ids = $filtro->competenciasEquivalentes[$id] ?? $this->competencias->idsQueAtendem($id);
            $consulta->whereHas('competencias', fn (Builder $query) => $query->whereIn('competencias.id', $ids));
        }

        if ($filtro->tipo !== null) {
            $consulta->where('tipo', $filtro->tipo->value);
        }

        if ($filtro->area !== null) {
            $consulta->where('area', $filtro->area->value);
        }

        if ($filtro->somenteComExperiencia) {
            $consulta->where('experiencia_na_area', true);
        }

        if ($filtro->busca !== null) {
            $consulta->where(fn (Builder $query) => $query
                ->where('nome', 'like', '%'.$filtro->busca.'%')
                ->orWhere('email', 'like', '%'.$filtro->busca.'%'));
        }

        return $consulta->orderBy('nome')->orderBy('id')->paginate($porPagina)->withQueryString();
    }

    public function detalhar(Candidato $candidato): Candidato
    {
        return $candidato->load([
            'competencias' => fn (BelongsToMany $query): BelongsToMany => $query->orderBy('nome'),
            'anotacoes.autor',
            'anotacoes.vaga',
            'perfilEnergia',
        ]);
    }

    public function contarAtivosPorTipo(): array
    {
        $totais = Candidato::query()->ativos()->select('tipo')->selectRaw('COUNT(*) as total')
            ->groupBy('tipo')->pluck('total', 'tipo');

        return [
            'servidores' => (int) ($totais[TipoCandidato::Servidor->value] ?? 0),
            'estagiarios' => (int) ($totais[TipoCandidato::Estagiario->value] ?? 0),
        ];
    }

    public function topCompetencias(int $limite = 8): Collection
    {
        return Competencia::query()->select(['id', 'nome'])->withCount([
            'candidatos as total' => fn (Builder $query) => $query->ativos(),
        ])->whereHas('candidatos', fn (Builder $query) => $query->ativos())
            ->orderByDesc('total')->orderBy('nome')->limit($limite)->get();
    }

    public function encontrarPorEmailOuCpf(?string $email, ?string $cpf): ?Candidato
    {
        if (blank($email) && blank($cpf)) {
            return null;
        }

        return Candidato::query()->where(function (Builder $query) use ($email, $cpf): void {
            if (filled($email)) {
                $query->where('email', $email);
            }

            if (filled($cpf)) {
                $query->orWhere('cpf', $cpf);
            }
        })->orderBy('id')->first();
    }
}
