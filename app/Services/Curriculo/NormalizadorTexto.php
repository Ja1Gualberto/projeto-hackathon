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

    /**
     * Identifica linhas que só trazem uma data ou um período ("2024", "03/2024 - 06/2024", "Mar 2020 a Atual").
     */
    public static function ehSomenteData(string $texto): bool
    {
        $data = '(?:(?:jan|fev|mar|abr|mai|jun|jul|ago|set|out|nov|dez)[a-z]*\.?(?:\s+de\s+|\s+|\/))?(?:\d{1,2}\/)?\d{4}';

        return preg_match("/^{$data}(?:\s*(?:-|a|ate)\s*(?:{$data}|atual|presente|hoje))?$/", self::normalizar($texto)) === 1;
    }
}
