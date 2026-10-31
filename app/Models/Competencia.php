<?php

namespace App\Models;

use App\Enums\Area;
use App\Enums\CategoriaCompetencia;
use Database\Factories\CompetenciaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable(['nome', 'slug', 'categoria', 'area', 'sinonimos', 'grupo', 'nivel'])]
class Competencia extends Model
{
    /** @use HasFactory<CompetenciaFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sinonimos' => '[]',
    ];

    protected static function booted(): void
    {
        static::creating(function (Competencia $competencia): void {
            if (blank($competencia->slug)) {
                $competencia->slug = static::gerarSlug($competencia->nome);
            }
        });
    }

    /**
     * Gera o slug preservando símbolos comuns em nomes de tecnologia (C# → c-sharp, C++ → c-plus-plus).
     */
    public static function gerarSlug(string $nome): string
    {
        return Str::slug(str_replace(['#', '+'], [' sharp', ' plus'], $nome));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'categoria' => CategoriaCompetencia::class,
            'area' => Area::class,
            'sinonimos' => 'array',
            'nivel' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Candidato, $this>
     */
    public function candidatos(): BelongsToMany
    {
        return $this->belongsToMany(Candidato::class, 'candidato_competencia')
            ->withPivot('origem')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Vaga, $this>
     */
    public function vagas(): BelongsToMany
    {
        return $this->belongsToMany(Vaga::class, 'competencia_vaga')
            ->withPivot('peso')
            ->withTimestamps();
    }

    /**
     * Idiomas têm grupo e nível; um nível maior atende a um nível menor do mesmo grupo.
     */
    public function ehIdioma(): bool
    {
        return $this->grupo !== null && $this->nivel !== null;
    }
}
