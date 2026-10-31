<?php

namespace App\Models;

use App\Enums\Area;
use App\Enums\StatusVaga;
use App\Enums\TipoCandidatoVaga;
use Database\Factories\VagaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'titulo',
    'area',
    'tipo_candidato',
    'descricao',
    'status',
    'energia_dinamico_analitico',
    'energia_individual_equipe',
    'energia_execucao_planejamento',
    'criada_por',
    'encerrada_em',
])]
class Vaga extends Model
{
    /** @use HasFactory<VagaFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'aberta',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'area' => Area::class,
            'tipo_candidato' => TipoCandidatoVaga::class,
            'status' => StatusVaga::class,
            'energia_dinamico_analitico' => 'integer',
            'energia_individual_equipe' => 'integer',
            'energia_execucao_planejamento' => 'integer',
            'encerrada_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsToMany<Competencia, $this>
     */
    public function competencias(): BelongsToMany
    {
        return $this->belongsToMany(Competencia::class, 'competencia_vaga')
            ->withPivot('peso')
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
     * @return BelongsTo<User, $this>
     */
    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criada_por');
    }

    public function estaAberta(): bool
    {
        return $this->status === StatusVaga::Aberta;
    }

    public function temPadraoEnergia(): bool
    {
        return $this->energia_dinamico_analitico !== null
            && $this->energia_individual_equipe !== null
            && $this->energia_execucao_planejamento !== null;
    }
}
