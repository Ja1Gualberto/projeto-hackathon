<?php

namespace App\Services\Curriculo;

use App\DTOs\ResultadoLeituraDTO;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Lê o texto selecionável de um PDF (research R3). Não usa OCR nem serviços externos.
 */
class LeitorPdf
{
    public function extrairTexto(string $caminhoAbsoluto): ResultadoLeituraDTO
    {
        if (! is_file($caminhoAbsoluto) || ! is_readable($caminhoAbsoluto)) {
            return ResultadoLeituraDTO::corrompido();
        }

        try {
            $texto = (new Parser)->parseFile($caminhoAbsoluto)->getText();
        } catch (Throwable) {
            return ResultadoLeituraDTO::corrompido();
        }

        $texto = trim(str_replace("\r", '', $texto));
        $caracteresVisiveis = mb_strlen((string) preg_replace('/\s+/u', '', $texto));

        if ($caracteresVisiveis < (int) config('banco_talentos.minimo_caracteres_texto')) {
            return ResultadoLeituraDTO::semTexto();
        }

        return ResultadoLeituraDTO::lido($texto);
    }
}
