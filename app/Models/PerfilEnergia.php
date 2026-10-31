<?php

namespace App\Models;

use App\Enums\OrigemPerfilEnergia;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perfil de energia sugerido para o candidato nos três eixos (1 a 5). O perfil desejado
 * da vaga fica nas colunas `energia_*` de `vagas`.
 */
#[Fillable([
    'candidato_id',
    'dinamico_analitico',
    'individual_equipe',
    'execucao_planejamento',
    'justificativa',
    'origem',
    'revisado_por',
    'analisado_em',
])]
class PerfilEnergia extends Model
{
    protected $table = 'perfis_energia';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'dinamico_analitico' => 'integer',
            'individual_equipe' => 'integer',
            'execucao_planejamento' => 'integer',
            'origem' => OrigemPerfilEnergia::class,
            'analisado_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Candidato, $this>
     */
    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
