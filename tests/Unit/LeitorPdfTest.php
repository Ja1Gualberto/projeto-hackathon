<?php

namespace Tests\Unit;

use App\DTOs\ResultadoLeituraDTO;
use App\Services\Curriculo\LeitorLayoutPdf;
use App\Services\Curriculo\LeitorPdf;
use Database\Seeders\Support\GeradorPdfCurriculo;
use RuntimeException;
use Smalot\PdfParser\Document;
use Tests\TestCase;

class LeitorPdfTest extends TestCase
{
    /** @var list<string> */
    private array $temporarios = [];

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $caminho) {
            @unlink($caminho);
        }

        parent::tearDown();
    }

    private function arquivo(string $conteudo): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'cv');
        file_put_contents($caminho, $conteudo);
        $this->temporarios[] = $caminho;

        return $caminho;
    }

    private function ler(string $conteudo): ResultadoLeituraDTO
    {
        return app(LeitorPdf::class)->extrairTexto($this->arquivo($conteudo));
    }

    public function test_le_o_texto_de_um_pdf_com_texto_selecionavel(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerar([
            'Ana Lima',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício)',
            'COMPETÊNCIAS',
            'Python, SQL, Git, Trabalho em equipe, Comunicação',
        ]));

        $this->assertSame(ResultadoLeituraDTO::LIDO, $resultado->situacao);
        $this->assertStringContainsString('Análise e Desenvolvimento de Sistemas', $resultado->texto);
        $this->assertStringContainsString('Python, SQL, Git', $resultado->texto);
    }

    /** @param list<string> $trechos */
    private function assertEmOrdem(array $trechos, string $texto): void
    {
        $posicao = -1;

        foreach ($trechos as $trecho) {
            $encontrada = mb_strpos($texto, $trecho);
            $this->assertNotFalse($encontrada, "Não encontrado: {$trecho}\n{$texto}");
            $this->assertGreaterThan($posicao, $encontrada, "Fora de ordem: {$trecho}\n{$texto}");
            $posicao = $encontrada;
        }
    }

    /** @return list<array{x: int, y: int, texto: string, tamanho?: int}> */
    private function duasColunasForaDeOrdem(): array
    {
        return [
            ['x' => 40, 'y' => 780, 'texto' => 'Títulos primeiro: Contato', 'tamanho' => 14],
            ['x' => 300, 'y' => 780, 'texto' => 'Educação', 'tamanho' => 14],
            ['x' => 300, 'y' => 620, 'texto' => 'Experiência', 'tamanho' => 14],
            ['x' => 300, 'y' => 760, 'texto' => 'Instituto Vale Digital (fictício)', 'tamanho' => 9],
            ['x' => 300, 'y' => 745, 'texto' => 'Análise e Desenvolvimento de Sistemas', 'tamanho' => 9],
            ['x' => 300, 'y' => 600, 'texto' => 'Estágio no Laboratório Horizonte (fictício)', 'tamanho' => 9],
            ['x' => 300, 'y' => 585, 'texto' => 'Atuação com sistemas internos da instituição', 'tamanho' => 9],
            ['x' => 40, 'y' => 760, 'texto' => 'ana.lima@exemplo.com.br', 'tamanho' => 9],
            ['x' => 40, 'y' => 745, 'texto' => '(00) 99000-0000', 'tamanho' => 9],
        ];
    }

    public function test_reconstroi_a_ordem_visual_de_duas_colunas_gravadas_fora_de_ordem(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerarComPosicoes($this->duasColunasForaDeOrdem()));

        $this->assertSame(ResultadoLeituraDTO::LIDO, $resultado->situacao);
        $this->assertEmOrdem([
            'Títulos primeiro: Contato',
            'ana.lima@exemplo.com.br',
            '(00) 99000-0000',
            'Educação',
            'Instituto Vale Digital (fictício)',
            'Análise e Desenvolvimento de Sistemas',
            'Experiência',
            'Estágio no Laboratório Horizonte (fictício)',
            'Atuação com sistemas internos da instituição',
        ], $resultado->texto);
    }

    public function test_le_o_texto_dentro_de_form_xobject(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerarComPosicoes($this->duasColunasForaDeOrdem(), dentroDeFormulario: true));

        $this->assertSame(ResultadoLeituraDTO::LIDO, $resultado->situacao);
        $this->assertEmOrdem(['ana.lima@exemplo.com.br', 'Educação', 'Atuação com sistemas internos'], $resultado->texto);
    }

    public function test_data_alinhada_a_direita_fica_na_linha_da_entrada_e_nao_vira_coluna(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerarComPosicoes([
            ['x' => 50, 'y' => 780, 'texto' => 'Instituto Vale Digital (fictício)'],
            ['x' => 480, 'y' => 780, 'texto' => '2023 - 2026'],
            ['x' => 50, 'y' => 765, 'texto' => 'Análise e Desenvolvimento de Sistemas'],
            ['x' => 50, 'y' => 750, 'texto' => 'Laboratório Horizonte (fictício)'],
            ['x' => 480, 'y' => 750, 'texto' => '2024 - 2025'],
            ['x' => 50, 'y' => 735, 'texto' => 'Estágio em desenvolvimento de sistemas'],
        ]));

        $this->assertStringContainsString("Instituto Vale Digital (fictício) 2023 - 2026\nAnálise e Desenvolvimento de Sistemas", $resultado->texto);
        $this->assertStringContainsString("Laboratório Horizonte (fictício) 2024 - 2025\nEstágio em desenvolvimento", $resultado->texto);
    }

    public function test_separa_blocos_lado_a_lado_dentro_de_uma_coluna(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerarComPosicoes([
            ['x' => 50, 'y' => 780, 'texto' => 'Certificados e outros cursos concluídos'],
            ['x' => 50, 'y' => 760, 'texto' => 'Curso de Banco de Dados'],
            ['x' => 320, 'y' => 760, 'texto' => 'Curso de Git e GitHub'],
            ['x' => 50, 'y' => 745, 'texto' => 'Duração do Curso: 40 horas'],
            ['x' => 320, 'y' => 745, 'texto' => 'Duração do Curso: 10 horas'],
        ]));

        $this->assertEmOrdem(['Curso de Banco de Dados', 'Duração do Curso: 40 horas', 'Curso de Git e GitHub', 'Duração do Curso: 10 horas'], $resultado->texto);
    }

    public function test_maior_titulo_da_pagina_vira_destaque_mesmo_em_duas_linhas(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerarComPosicoes([
            ['x' => 50, 'y' => 700, 'texto' => 'Conteúdo do currículo fictício com bastante texto para a leitura', 'tamanho' => 10],
            ['x' => 50, 'y' => 685, 'texto' => 'Mais uma linha do conteúdo do currículo fictício usado no teste', 'tamanho' => 10],
            ['x' => 50, 'y' => 780, 'texto' => 'Maria', 'tamanho' => 24],
            ['x' => 50, 'y' => 750, 'texto' => 'Clara Souza', 'tamanho' => 24],
        ]));

        $this->assertSame('Maria Clara Souza', $resultado->destaque);
    }

    public function test_documento_sem_titulo_de_destaque_nao_indica_nome(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerar([
            'Ana Lima',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício)',
            'Python, SQL, Git, Trabalho em equipe, Comunicação',
        ]));

        $this->assertNull($resultado->destaque);
    }

    public function test_recorre_a_ordem_do_arquivo_quando_o_layout_nao_e_confiavel(): void
    {
        $this->app->bind(LeitorLayoutPdf::class, fn () => new class extends LeitorLayoutPdf
        {
            public function ler(Document $documento): ?array
            {
                throw new RuntimeException('Falha simulada na leitura por posição.');
            }
        });

        $resultado = $this->ler((new GeradorPdfCurriculo)->gerar([
            'Ana Lima',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício)',
            'Python, SQL, Git, Trabalho em equipe, Comunicação',
        ]));

        $this->assertSame(ResultadoLeituraDTO::LIDO, $resultado->situacao);
        $this->assertStringContainsString('Análise e Desenvolvimento de Sistemas', $resultado->texto);
        $this->assertNull($resultado->destaque);
    }

    public function test_recorre_a_ordem_do_arquivo_quando_o_layout_perde_texto(): void
    {
        $this->app->bind(LeitorLayoutPdf::class, fn () => new class extends LeitorLayoutPdf
        {
            public function ler(Document $documento): ?array
            {
                return ['texto' => 'Ana Lima', 'destaque' => 'Pessoa Errada'];
            }
        });

        $resultado = $this->ler((new GeradorPdfCurriculo)->gerar([
            'Ana Lima',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício)',
            'Python, SQL, Git, Trabalho em equipe, Comunicação',
        ]));

        $this->assertStringContainsString('Análise e Desenvolvimento de Sistemas', $resultado->texto);
        $this->assertNull($resultado->destaque);
    }

    public function test_pdf_sem_texto_e_identificado_como_digitalizado(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerarSemTexto());

        $this->assertSame(ResultadoLeituraDTO::SEM_TEXTO, $resultado->situacao);
        $this->assertNull($resultado->texto);
    }

    public function test_texto_com_menos_de_oitenta_caracteres_e_tratado_como_sem_texto(): void
    {
        $resultado = $this->ler((new GeradorPdfCurriculo)->gerar(['Somente trinta caracteres aqui']));

        $this->assertSame(ResultadoLeituraDTO::SEM_TEXTO, $resultado->situacao);
    }

    public function test_arquivo_invalido_e_identificado_como_corrompido(): void
    {
        $resultado = $this->ler("%PDF-1.4\nconteúdo truncado sem objetos");

        $this->assertSame(ResultadoLeituraDTO::CORROMPIDO, $resultado->situacao);
        $this->assertNull($resultado->texto);
    }

    public function test_arquivo_inexistente_e_identificado_como_corrompido(): void
    {
        $resultado = app(LeitorPdf::class)->extrairTexto(sys_get_temp_dir().'/nao-existe-'.uniqid().'.pdf');

        $this->assertSame(ResultadoLeituraDTO::CORROMPIDO, $resultado->situacao);
    }
}
