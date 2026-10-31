<?php

namespace App\Models;

use App\Enums\EtapaSelecao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico imutável de mudanças de etapa: quem moveu, quando, de onde e para onde (FR-024).
 */
#[Fillable(['participacao_id', 'user_id', 'de', 'para'])]
class MovimentacaoEtapa extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movimentacoes_etapa';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'de' => EtapaSelecao::class,
            'para' => EtapaSelecao::class,
        ];
    }

    /**
     * @return BelongsTo<Participacao, $this>
     */
    public function participacao(): BelongsTo
    {
        return $this->belongsTo(Participacao::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
