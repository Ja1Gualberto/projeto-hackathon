<?php

namespace App\Console\Commands;

use Database\Seeders\Support\GeradorPdfCurriculo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Gera os PDFs fictícios usados no envio ao vivo da demonstração (research R15, quickstart cenário B).
 */
class GerarCurriculosDemoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'demo:curriculos {--diretorio= : Pasta de destino (padrão: database/seeders/curriculos)}';

    /**
     * @var string
     */
    protected $description = 'Gera os currículos fictícios em PDF para a demonstração da importação';

    public function handle(GeradorPdfCurriculo $gerador): int
    {
        $diretorio = rtrim((string) ($this->option('diretorio') ?: database_path('seeders/curriculos')), '/\\');
        File::ensureDirectoryExists($diretorio);

        $arquivos = [
            'demo-curriculo.pdf' => $gerador->gerar($this->curriculoCompleto()),
            'demo-colunas.pdf' => $gerador->gerarComPosicoes($this->curriculoEmColunas(), dentroDeFormulario: true),
            'demo-escaneado.pdf' => $gerador->gerarSemTexto(),
            'demo-duplicado.pdf' => $gerador->gerar($this->curriculoDuplicado()),
        ];

        foreach ($arquivos as $nome => $conteudo) {
            File::put("{$diretorio}/{$nome}", $conteudo);
        }

        $this->components->info('Currículos de demonstração gerados em '.$diretorio.'.');
        $this->components->bulletList([
            'demo-curriculo.pdf — estagiária fictícia de desenvolvimento (leitura completa).',
            'demo-colunas.pdf — layout em duas colunas, com o texto fora de ordem no arquivo.',
            'demo-escaneado.pdf — PDF sem texto (preenchimento manual).',
            'demo-duplicado.pdf — mesmo e-mail de Ana Lima, já cadastrada (duplicidade).',
        ]);

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function curriculoCompleto(): array
    {
        return [
            'Larissa Mendes',
            'larissa.mendes@exemplo.com.br | (00) 98000-0001',
            'Vila Horizonte — cidade fictícia',
            'Data de nascimento: 12/05/2004',
            'RESUMO',
            'Estudante de Análise e Desenvolvimento de Sistemas em busca de estágio na SETIC.',
            'FORMAÇÃO',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício), 2023–2026',
            'Curso livre de versionamento com Git (20h)',
            'EXPERIÊNCIA PROFISSIONAL',
            'Monitoria no Laboratório Horizonte (fictício): automações em Python e consultas SQL',
            'PROJETOS',
            'Painel de chamados com Python e SQL para a feira acadêmica',
            'https://github.com/larissa-mendes-demo',
            'COMPETÊNCIAS',
            'Python, SQL, Git, Kubernetes',
            'Trabalho em equipe, Comunicação',
            'IDIOMAS',
            'Inglês intermediário',
        ];
    }

    /**
     * Barra lateral e coluna principal, gravadas agrupadas por tamanho de fonte, como fazem as
     * ferramentas de design: no arquivo, todos os títulos vêm antes de todo o conteúdo.
     *
     * @return list<array{x: int, y: int, texto: string, tamanho: int}>
     */
    private function curriculoEmColunas(): array
    {
        $trechos = [
            ['x' => 40, 'y' => 790, 'tamanho' => 24, 'texto' => 'Maria'],
            ['x' => 40, 'y' => 762, 'tamanho' => 24, 'texto' => 'Clara Souza'],
            ['x' => 40, 'y' => 742, 'tamanho' => 11, 'texto' => 'Técnica em Informática'],
            ['x' => 40, 'y' => 700, 'tamanho' => 14, 'texto' => 'Contato'],
            ['x' => 40, 'y' => 682, 'tamanho' => 9, 'texto' => '(00) 9 9800-0004'],
            ['x' => 40, 'y' => 668, 'tamanho' => 9, 'texto' => 'maria.clara@exemplo.com.br'],
            ['x' => 40, 'y' => 654, 'tamanho' => 9, 'texto' => 'github.com/maria-clara-demo'],
            ['x' => 40, 'y' => 610, 'tamanho' => 14, 'texto' => 'Sobre mim'],
            ['x' => 40, 'y' => 592, 'tamanho' => 9, 'texto' => 'Estudante de tecnologia em busca'],
            ['x' => 40, 'y' => 580, 'tamanho' => 9, 'texto' => 'de estágio na SETIC (fictício).'],
            ['x' => 40, 'y' => 540, 'tamanho' => 14, 'texto' => 'Habilidades'],
            ['x' => 40, 'y' => 522, 'tamanho' => 9, 'texto' => 'Python, SQL e Git'],
            ['x' => 40, 'y' => 508, 'tamanho' => 9, 'texto' => 'Laravel e Docker'],
            ['x' => 40, 'y' => 494, 'tamanho' => 9, 'texto' => 'Experiência auxiliando colegas'],
            ['x' => 40, 'y' => 480, 'tamanho' => 9, 'texto' => 'Trabalho em equipe'],

            ['x' => 250, 'y' => 790, 'tamanho' => 14, 'texto' => 'Educação'],
            ['x' => 250, 'y' => 772, 'tamanho' => 9, 'texto' => 'Instituto Vale Digital (fictício)'],
            ['x' => 500, 'y' => 772, 'tamanho' => 9, 'texto' => '2023 - 2026'],
            ['x' => 250, 'y' => 758, 'tamanho' => 9, 'texto' => 'Análise e Desenvolvimento de Sistemas - Cursando'],
            ['x' => 250, 'y' => 744, 'tamanho' => 9, 'texto' => 'Técnico em Informática - Concluído'],
            ['x' => 250, 'y' => 710, 'tamanho' => 14, 'texto' => 'Experiência'],
            ['x' => 250, 'y' => 692, 'tamanho' => 9, 'texto' => 'Estágio no Laboratório Horizonte (fictício) em'],
            ['x' => 510, 'y' => 686, 'tamanho' => 9, 'texto' => '2025'],
            ['x' => 250, 'y' => 680, 'tamanho' => 9, 'texto' => 'desenvolvimento de sistemas'],
            ['x' => 250, 'y' => 664, 'tamanho' => 9, 'texto' => 'Apoio ao desenvolvimento de sistemas internos em Laravel e Python,'],
            ['x' => 250, 'y' => 652, 'tamanho' => 9, 'texto' => 'com consultas em SQL e versionamento no Git. Participei também'],
            ['x' => 250, 'y' => 640, 'tamanho' => 9, 'texto' => 'da documentação e do atendimento aos usuários.'],
            ['x' => 250, 'y' => 616, 'tamanho' => 9, 'texto' => 'Projeto: “Sistema de Agendamento de Salas para a'],
            ['x' => 510, 'y' => 610, 'tamanho' => 9, 'texto' => '2025'],
            ['x' => 250, 'y' => 604, 'tamanho' => 9, 'texto' => 'Coordenação”'],
            ['x' => 250, 'y' => 588, 'tamanho' => 9, 'texto' => 'Aplicação web para reservar salas, com painel de ocupação e'],
            ['x' => 250, 'y' => 576, 'tamanho' => 9, 'texto' => 'relatórios mensais para a coordenação.'],
            ['x' => 250, 'y' => 540, 'tamanho' => 14, 'texto' => 'Certificados'],
            ['x' => 250, 'y' => 522, 'tamanho' => 9, 'texto' => 'Curso de Extensão em Banco'],
            ['x' => 250, 'y' => 510, 'tamanho' => 9, 'texto' => 'de Dados'],
            ['x' => 250, 'y' => 498, 'tamanho' => 9, 'texto' => 'Duração do Curso: 40 horas'],
            ['x' => 420, 'y' => 522, 'tamanho' => 9, 'texto' => 'Git e GitHub na Prática'],
            ['x' => 420, 'y' => 510, 'tamanho' => 9, 'texto' => 'Duração do Curso: 10 horas'],
        ];

        usort($trechos, fn (array $a, array $b): int => $b['tamanho'] <=> $a['tamanho']);

        return $trechos;
    }

    /**
     * @return list<string>
     */
    private function curriculoDuplicado(): array
    {
        return [
            'Ana Lima',
            'ana.lima@exemplo.com.br | (00) 90000-0003',
            'FORMAÇÃO',
            'Cursando Sistemas de Informação — Instituto Vale Digital (fictício)',
            'EXPERIÊNCIA PROFISSIONAL',
            'Estágio em desenvolvimento web com React e Docker no Laboratório Horizonte (fictício)',
            'PROJETOS',
            'Projeto acadêmico em Desenvolvimento com Python.',
            'COMPETÊNCIAS',
            'Python, SQL, Git, React, Docker',
            'Trabalho em equipe',
        ];
    }
}
