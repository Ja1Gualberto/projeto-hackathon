<?php

namespace App\Services;

use App\Enums\CategoriaCompetencia;
use App\Models\Competencia;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use App\Services\Curriculo\NormalizadorTexto;
use Illuminate\Database\Eloquent\Collection;

/**
 * Manutenção mínima do catálogo padronizado (FR-014): novas competências só entram por decisão
 * do recrutador, a partir das sugestões da leitura do currículo.
 */
class CompetenciaService
{
    public function __construct(private CompetenciaRepositoryInterface $competencias) {}

    public function adicionarAoCatalogo(string $nome, CategoriaCompetencia $categoria): Competencia
    {
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));

        return $this->competencias->criar([
            'nome' => $nome,
            'categoria' => $categoria,
            'sinonimos' => [NormalizadorTexto::normalizar($nome)],
        ]);
    }

    /** @return array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>} */
    public function catalogo(): array
    {
        return $this->competencias->todasAgrupadasPorCategoria();
    }
}
