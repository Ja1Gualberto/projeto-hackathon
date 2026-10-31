<?php

namespace App\Models;

use App\Enums\Area;
use App\Enums\MeioConsentimento;
use App\Enums\StatusCandidato;
use App\Enums\TipoCandidato;
use App\Enums\TipoCandidatoVaga;
use Carbon\CarbonInterface;
use Database\Factories\CandidatoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'tipo',
    'area',
    'status',
    'nome',
    'email',
    'telefone',
    'cpf',
    'data_nascimento',
    'formacoes',
    'experiencias',
    'experiencia_na_area',
    'projetos',
    'links',
    'texto_curriculo',
    'curriculo_arquivo',
    'curriculo_nome_original',
    'curriculo_tamanho',
    'consentimento_meio',
    'consentimento_em',
    'consentimento_registrado_por',
    'dados_atualizados_em',
])]
#[Hidden([
    'nome',
    'email',
    'telefone',
    'cpf',
    'data_nascimento',
    'links',
    'texto_curriculo',
    'curriculo_arquivo',
])]
class Candidato extends Model
{
    /** @use HasFactory<CandidatoFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'ativo',
        'formacoes' => '[]',
        'experiencias' => '[]',
        'projetos' => '[]',
        'links' => '[]',
        'experiencia_na_area' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Candidato $candidato): void {
            if (blank($candidato->codigo)) {
                $candidato->codigo = static::gerarCodigoUnico();
            }

            if ($candidato->dados_atualizados_em === null) {
                $candidato->dados_atualizados_em = now();
            }
        });
    }

    /**
     * Gera o código anônimo `CAND-NNNN` com dígitos aleatórios, para não revelar a ordem de cadastro.
     */
    public static function gerarCodigoUnico(): string
    {
        do {
            $codigo = 'CAND-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::query()->where('codigo', $codigo)->exists());

        return $codigo;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoCandidato::class,
            'area' => Area::class,
            'status' => StatusCandidato::class,
            'consentimento_meio' => MeioConsentimento::class,
            'formacoes' => 'array',
            'experiencias' => 'array',
            'projetos' => 'array',
            'links' => 'array',
            'experiencia_na_area' => 'boolean',
            'curriculo_tamanho' => 'integer',
            'data_nascimento' => 'date',
            'consentimento_em' => 'date',
            'dados_atualizados_em' => 'datetime',
        ];
    }

    /**
     * @return Attribute<int|null, never>
     */
    protected function idade(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->data_nascimento?->age);
    }

    /**
     * Data limite de retenção: última atualização dos dados + prazo configurado (2 anos).
     *
     * @return Attribute<CarbonInterface|null, never>
     */
    protected function retencaoAte(): Attribute
    {
        return Attribute::get(fn (): ?CarbonInterface => $this->dados_atualizados_em
            ?->copy()
            ->addYears((int) config('banco_talentos.retencao_anos')));
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function retencaoVencida(): Attribute
    {
        return Attribute::get(fn (): bool => $this->retencao_ate !== null && $this->retencao_ate->isPast());
    }

    /**
     * @param  Builder<Candidato>  $query
     */
    #[Scope]
    protected function ativos(Builder $query): void
    {
        $query->where('status', StatusCandidato::Ativo->value);
    }

    /**
     * Candidatos ativos do tipo aceito pela vaga.
     *
     * @param  Builder<Candidato>  $query
     */
    #[Scope]
    protected function elegiveisPara(Builder $query, Vaga $vaga): void
    {
        $query->where('status', StatusCandidato::Ativo->value);

        if ($vaga->tipo_candidato !== TipoCandidatoVaga::Ambos) {
            $query->where('tipo', $vaga->tipo_candidato->value);
        }
    }

    /**
     * Cadastros cuja data de retenção já passou (revisão de retenção pendente).
     *
     * @param  Builder<Candidato>  $query
     */
    #[Scope]
    protected function comRetencaoVencida(Builder $query): void
    {
        $query->where('dados_atualizados_em', '<', now()->subYears((int) config('banco_talentos.retencao_anos')));
    }

    /**
     * @return BelongsToMany<Competencia, $this>
     */
    public function competencias(): BelongsToMany
    {
        return $this->belongsToMany(Competencia::class, 'candidato_competencia')
            ->withPivot('origem')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Participacao, $this>
     */
    public function participacoes(): HasMany
    {
        return $this->hasMany(Participacao::class);
    }

    /**
     * @return HasMany<Anotacao, $this>
     */
    public function anotacoes(): HasMany
    {
        return $this->hasMany(Anotacao::class);
    }

    /**
     * @return HasOne<PerfilEnergia, $this>
     */
    public function perfilEnergia(): HasOne
    {
        return $this->hasOne(PerfilEnergia::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function consentimentoRegistradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consentimento_registrado_por');
    }
}
