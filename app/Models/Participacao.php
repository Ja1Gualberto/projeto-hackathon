<?php

namespace App\Models;

use App\Enums\EtapaSelecao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Participação de um candidato em uma vaga. A linha só existe a partir da primeira
 * movimentação; sem linha, o candidato elegível está em triagem (research R8).
 */
#[Fillable(['vaga_id', 'candidato_id', 'etapa'])]
class Participacao extends Model
{
    protected $table = 'participacoes';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'etapa' => EtapaSelecao::class,
        ];
    }

    /**
     * @return BelongsTo<Vaga, $this>
     */
    public function vaga(): BelongsTo
    {
        return $this->belongsTo(Vaga::class);
    }

    /**
     * @return BelongsTo<Candidato, $this>
     */
    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class);
    }

    /**
     * @return HasMany<MovimentacaoEtapa, $this>
     */
    public function movimentacoes(): HasMany
    {
        return $this->hasMany(MovimentacaoEtapa::class);
    }
}
