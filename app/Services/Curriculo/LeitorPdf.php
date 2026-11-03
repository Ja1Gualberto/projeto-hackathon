<?php

namespace App\Services\Curriculo;

use App\DTOs\ResultadoLeituraDTO;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lê o texto selecionável de um PDF (research R3). Não usa OCR nem serviços externos.
 * Prefere a ordem visual da página; se ela não for confiável, usa a ordem gravada no arquivo.
 */
class LeitorPdf
{
    /** Parcela mínima do texto do arquivo que a leitura por posição precisa recuperar. */
    private const COBERTURA_MINIMA_LAYOUT = 0.8;

    public function __construct(private LeitorLayoutPdf $layout) {}

    public function extrairTexto(string $caminhoAbsoluto): ResultadoLeituraDTO
    {
        if (! is_file($caminhoAbsoluto) || ! is_readable($caminhoAbsoluto)) {
            return ResultadoLeituraDTO::corrompido();
        }

        try {
            $documento = (new Parser)->parseFile($caminhoAbsoluto);
            $texto = $documento->getText();
        } catch (Throwable) {
            return ResultadoLeituraDTO::corrompido();
        }

        $texto = trim(str_replace("\r", '', $texto));
        $destaque = null;
        $layout = $this->lerLayout($documento);

        if ($layout !== null && $this->caracteresVisiveis($layout['texto']) >= self::COBERTURA_MINIMA_LAYOUT * $this->caracteresVisiveis($texto)) {
            ['texto' => $texto, 'destaque' => $destaque] = $layout;
        }

        if ($this->caracteresVisiveis($texto) < (int) config('banco_talentos.minimo_caracteres_texto')) {
            return ResultadoLeituraDTO::semTexto();
        }

        return ResultadoLeituraDTO::lido($texto, $destaque);
    }

    /**
     * @return array{texto: string, destaque: ?string}|null
     */
    private function lerLayout(Document $documento): ?array
    {
        try {
            return $this->layout->ler($documento);
        } catch (Throwable) {
            return null;
        }
    }

    private function caracteresVisiveis(string $texto): int
    {
        return mb_strlen((string) preg_replace('/\s+/u', '', $texto));
    }
}
