<?php

namespace Tests\Unit;

use App\DTOs\ResultadoLeituraDTO;
use App\Services\Curriculo\LeitorPdf;
use Database\Seeders\Support\GeradorPdfCurriculo;
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
