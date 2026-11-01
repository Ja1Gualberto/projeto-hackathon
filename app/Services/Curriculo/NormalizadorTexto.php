<?php

namespace App\Services\Curriculo;

use Illuminate\Support\Str;

/**
 * Normalização compartilhada entre o extrator e o catálogo: minúsculas, sem acentos e com
 * espaços simples. É o formato em que os sinônimos das competências ficam guardados.
 */
final class NormalizadorTexto
{
    public static function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/', ' ', mb_strtolower(Str::ascii($texto))));
    }
}
