<?php

namespace Database\Seeders\Support;

/**
 * Escritor mínimo de PDF 1.4 com texto selecionável, sem dependências.
 *
 * Usado pelos seeders (currículos fictícios dos candidatos) e pelos testes (PDFs com
 * texto conhecido). Usa a fonte padrão Helvetica com WinAnsiEncoding, que cobre os
 * acentos do português.
 */
final class GeradorPdfCurriculo
{
    private const LINHAS_POR_PAGINA = 48;

    private const TAMANHO_FONTE = 11;

    private const ENTRELINHA = 14;

    /**
     * Gera um PDF com uma linha de texto por item, quebrando a página a cada 48 linhas.
     *
     * @param  list<string>  $linhas
     */
    public function gerar(array $linhas): string
    {
        $linhas = $linhas === [] ? [''] : array_values($linhas);

        $conteudos = array_map(
            fn (array $linhasDaPagina): string => $this->conteudoComTexto($linhasDaPagina),
            array_chunk($linhas, self::LINHAS_POR_PAGINA),
        );

        return $this->montarDocumento($conteudos);
    }

    /**
     * Gera um PDF válido só com formas desenhadas, sem nenhum operador de texto,
     * simulando um currículo digitalizado (imagem).
     */
    public function gerarSemTexto(): string
    {
        $conteudo = implode("\n", [
            '0.85 g',
            '50 700 495 70 re f',
            '0.65 g',
            '50 640 320 18 re f',
            '50 605 420 18 re f',
            '50 570 380 18 re f',
            '50 535 260 18 re f',
        ])."\n";

        return $this->montarDocumento([$conteudo]);
    }

    /**
     * @param  list<string>  $linhas
     */
    private function conteudoComTexto(array $linhas): string
    {
        $comandos = [
            'BT',
            '/F1 '.self::TAMANHO_FONTE.' Tf',
            self::ENTRELINHA.' TL',
            '50 790 Td',
        ];

        foreach ($linhas as $linha) {
            $comandos[] = '('.$this->escaparTexto($linha).') Tj T*';
        }

        $comandos[] = 'ET';

        return implode("\n", $comandos)."\n";
    }

    private function escaparTexto(string $texto): string
    {
        $texto = str_replace(["\r", "\n", "\t"], ' ', $texto);
        $texto = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);
    }

    /**
     * Monta catálogo, árvore de páginas, fonte, páginas e streams, com a tabela xref.
     *
     * @param  list<string>  $conteudosDasPaginas
     */
    private function montarDocumento(array $conteudosDasPaginas): string
    {
        $objetos = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $proximoNumero = 4;
        $referenciasDePaginas = [];

        foreach ($conteudosDasPaginas as $conteudo) {
            $numeroPagina = $proximoNumero++;
            $numeroConteudo = $proximoNumero++;
            $referenciasDePaginas[] = "{$numeroPagina} 0 R";

            $objetos[$numeroPagina] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
                ."/Resources << /Font << /F1 3 0 R >> >> /Contents {$numeroConteudo} 0 R >>";
            $objetos[$numeroConteudo] = '<< /Length '.strlen($conteudo)." >>\nstream\n{$conteudo}endstream";
        }

        $objetos[2] = '<< /Type /Pages /Kids ['.implode(' ', $referenciasDePaginas).'] /Count '
            .count($referenciasDePaginas).' >>';

        ksort($objetos);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $deslocamentos = [];

        foreach ($objetos as $numero => $corpo) {
            $deslocamentos[$numero] = strlen($pdf);
            $pdf .= "{$numero} 0 obj\n{$corpo}\nendobj\n";
        }

        $inicioXref = strlen($pdf);
        $quantidade = count($objetos) + 1;

        $pdf .= "xref\n0 {$quantidade}\n0000000000 65535 f \n";

        foreach ($deslocamentos as $deslocamento) {
            $pdf .= sprintf("%010d 00000 n \n", $deslocamento);
        }

        return $pdf."trailer\n<< /Size {$quantidade} /Root 1 0 R >>\nstartxref\n{$inicioXref}\n%%EOF\n";
    }
}
