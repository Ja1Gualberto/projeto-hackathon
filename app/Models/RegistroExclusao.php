<?php

namespace App\Models;

use App\Enums\MotivoExclusao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prova mínima de que uma exclusão definitiva ocorreu, sem nenhum dado do titular (FR-030).
 */
#[Fillable(['user_id', 'motivo', 'excluido_em'])]
class RegistroExclusao extends Model
{
    public $timestamps = false;

    protected $table = 'registros_exclusao';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'motivo' => MotivoExclusao::class,
            'excluido_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
