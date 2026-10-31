<?php

namespace App\Models;

use Database\Factories\AnotacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['candidato_id', 'vaga_id', 'user_id', 'texto'])]
class Anotacao extends Model
{
    /** @use HasFactory<AnotacaoFactory> */
    use HasFactory;

    protected $table = 'anotacoes';

    /**
     * @return BelongsTo<Candidato, $this>
     */
    public function candidato(): BelongsTo
    {
        return $this->belongsTo(Candidato::class);
    }

    /**
     * @return BelongsTo<Vaga, $this>
     */
    public function vaga(): BelongsTo
    {
        return $this->belongsTo(Vaga::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsToMany<Competencia, $this>
     */
    public function competenciasObservadas(): BelongsToMany
    {
        return $this->belongsToMany(Competencia::class, 'anotacao_competencia');
    }
}
