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
     * Competências com logotipo em `public/images/competencias/{slug}.svg`. As demais, como as
     * comportamentais, não têm uma marca que as identifique e são exibidas pelo nome.
     *
     * @var list<string>
     */
    public const SLUGS_COM_ICONE = [
        'c-sharp', 'docker', 'excel-avancado', 'figma', 'git', 'java', 'javascript', 'laravel', 'linux', 'mysql', 'net',
        'node-js', 'php', 'postgresql', 'power-bi', 'python', 'react', 'sql', 'typescript', 'vue-js', 'windows',
    ];

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
                $competencia->slug = static::slugDisponivel(static::gerarSlug($competencia->nome) ?: 'competencia');
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
     * Nomes diferentes podem gerar o mesmo slug (ex.: incluídos pelo recrutador); acrescenta um sufixo.
     */
    private static function slugDisponivel(string $base): string
    {
        $slug = $base;
        $sufixo = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$sufixo}";
            $sufixo++;
        }

        return $slug;
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

    public function iconeUrl(): ?string
    {
        return in_array($this->slug, self::SLUGS_COM_ICONE, true) ? asset("images/competencias/{$this->slug}.svg") : null;
    }

    /**
     * Idiomas têm grupo e nível; um nível maior atende a um nível menor do mesmo grupo.
     */
    public function ehIdioma(): bool
    {
        return $this->grupo !== null && $this->nivel !== null;
    }
}
