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
            'demo-escaneado.pdf' => $gerador->gerarSemTexto(),
            'demo-duplicado.pdf' => $gerador->gerar($this->curriculoDuplicado()),
        ];

        foreach ($arquivos as $nome => $conteudo) {
            File::put("{$diretorio}/{$nome}", $conteudo);
        }

        $this->components->info('Currículos de demonstração gerados em '.$diretorio.'.');
        $this->components->bulletList([
            'demo-curriculo.pdf — estagiária fictícia de desenvolvimento (leitura completa).',
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
