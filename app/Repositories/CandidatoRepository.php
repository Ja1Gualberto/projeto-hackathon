<?php

namespace App\Repositories;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\MotivoExclusao;
use App\Enums\OrigemCompetencia;
use App\Enums\OrigemPerfilEnergia;
use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\PerfilEnergia;
use App\Models\RegistroExclusao;
use App\Models\User;
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
        $consulta = Candidato::query()->with([
            'competencias' => fn (BelongsToMany $query): BelongsToMany => $query->orderBy('nome'),
            'participacoes.vaga',
        ]);

        if ($filtro->situacao === 'desativados') {
            $consulta->where('status', StatusCandidato::Desativado);
        } else {
            $consulta->ativos();

            if ($filtro->situacao === 'retencao') {
                $consulta->comRetencaoVencida();
            }
        }

        foreach ($filtro->competencias as $id) {
            $ids = $filtro->competenciasEquivalentes[$id] ?? $this->competencias->idsQueAtendem($id);
            $consulta->whereHas('competencias', fn (Builder $query) => $query->whereIn('competencias.id', $ids));
        }

        if ($filtro->vagaId !== null) {
            $consulta->whereHas('participacoes', fn (Builder $query) => $query->where('vaga_id', $filtro->vagaId));
        }

        if ($filtro->excluirVagaId !== null) {
            $consulta->whereDoesntHave('participacoes', fn (Builder $query) => $query->where('vaga_id', $filtro->excluirVagaId));
        }

        if ($filtro->area !== null) {
            $consulta->where('area', $filtro->area->value);
        }

        if ($filtro->somenteComExperiencia) {
            $consulta->where('experiencia_na_area', true);
        }

        if ($filtro->busca !== null) {
            $consulta->where(fn (Builder $query) => $query
                ->where('codigo', 'like', '%'.$filtro->busca.'%')
                ->orWhere('nome', 'like', '%'.$filtro->busca.'%')
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
            'consentimentoRegistradoPor',
            'participacoes.vaga',
        ]);
    }

    public function contarAtivos(): int
    {
        return Candidato::query()->ativos()->count();
    }

    public function salvarPerfilEnergia(Candidato $candidato, array $atributos): PerfilEnergia
    {
        $perfil = $candidato->perfilEnergia()->updateOrCreate([], $atributos);
        $candidato->setRelation('perfilEnergia', $perfil);

        return $perfil;
    }

    public function candidatosParaAnaliseEnergia(bool $todos): Collection
    {
        return Candidato::query()->ativos()->with(['competencias', 'perfilEnergia'])
            ->where(function (Builder $query) use ($todos): void {
                $query->whereDoesntHave('perfilEnergia');
                if ($todos) {
                    $query->orWhereHas('perfilEnergia', fn (Builder $perfil) => $perfil->where('origem', OrigemPerfilEnergia::Ia));
                }
            })->orderBy('id')->get();
    }

    public function topCompetencias(int $limite = 8): Collection
    {
        return Competencia::query()->select(['id', 'nome', 'slug'])->withCount([
            'candidatos as total' => fn (Builder $query) => $query->ativos(),
        ])->whereHas('candidatos', fn (Builder $query) => $query->ativos())
            ->orderByDesc('total')->orderBy('nome')->limit($limite)->get();
    }

    public function contarRetencaoVencida(): int
    {
        return Candidato::query()->ativos()->comRetencaoVencida()->count();
    }

    public function excluir(Candidato $candidato, MotivoExclusao $motivo, User $responsavel): void
    {
        $candidato->delete();

        RegistroExclusao::query()->create([
            'user_id' => $responsavel->id,
            'motivo' => $motivo,
            'excluido_em' => now(),
        ]);
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

    public function encontrar(int $id): ?Candidato
    {
        return Candidato::query()->find($id);
    }

    public function criar(array $atributos): Candidato
    {
        return Candidato::query()->create($atributos);
    }

    public function atualizar(Candidato $candidato, array $atributos): Candidato
    {
        $candidato->fill($atributos)->save();

        return $candidato;
    }

    public function origensDasCompetencias(Candidato $candidato): array
    {
        return $candidato->competencias()->get()
            ->mapWithKeys(fn (Competencia $competencia): array => [
                $competencia->id => OrigemCompetencia::from($competencia->pivot->origem),
            ])->all();
    }

    public function sincronizarCompetencias(Candidato $candidato, array $origens): void
    {
        $candidato->competencias()->sync(array_map(
            fn (OrigemCompetencia $origem): array => ['origem' => $origem->value],
            $origens,
        ));
    }
}
