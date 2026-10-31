<?php

namespace Database\Seeders;

use App\Enums\Area;
use App\Enums\CategoriaCompetencia;
use App\Models\Competencia;
use Illuminate\Database\Seeder;

/**
 * Catálogo padronizado de competências (FR-014). Os sinônimos ficam em minúsculas e sem
 * acento, no mesmo formato usado pelo extrator de currículos.
 */
class CompetenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->catalogo() as $item) {
            Competencia::updateOrCreate(
                ['nome' => $item['nome']],
                [
                    'categoria' => $item['categoria'],
                    'area' => $item['area'],
                    'sinonimos' => $item['sinonimos'],
                    'grupo' => $item['grupo'] ?? null,
                    'nivel' => $item['nivel'] ?? null,
                ],
            );
        }
    }

    /**
     * @return list<array{nome: string, categoria: CategoriaCompetencia, area: Area|null, sinonimos: list<string>, grupo?: string, nivel?: int}>
     */
    private function catalogo(): array
    {
        return [
            ...$this->hardSkills(),
            ...$this->idiomas(),
            ...$this->softSkills(),
        ];
    }

    /**
     * @return list<array{nome: string, categoria: CategoriaCompetencia, area: Area, sinonimos: list<string>}>
     */
    private function hardSkills(): array
    {
        $porArea = [
            Area::Desenvolvimento->value => [
                'Python' => ['python', 'python3', 'django', 'flask', 'fastapi'],
                'PHP' => ['php'],
                'Laravel' => ['laravel'],
                'JavaScript' => ['javascript', 'js', 'ecmascript'],
                'TypeScript' => ['typescript'],
                'React' => ['react', 'react.js', 'reactjs', 'react native'],
                'Vue.js' => ['vue', 'vue.js', 'vuejs', 'nuxt'],
                'Node.js' => ['node.js', 'nodejs', 'node', 'express'],
                'Java' => ['java', 'spring', 'spring boot'],
                'C#' => ['c#', 'csharp'],
                '.NET' => ['.net', 'dotnet', 'asp.net'],
                'API REST' => ['api rest', 'rest api', 'apis rest', 'restful', 'api restful'],
                'Testes automatizados' => ['testes automatizados', 'testes unitarios', 'teste unitario', 'phpunit', 'jest', 'tdd'],
                'Git' => ['git', 'github', 'gitlab', 'versionamento'],
            ],
            Area::Dados->value => [
                'SQL' => ['sql', 'mysql', 'postgresql', 'postgres', 'sql server', 'oracle', 'mariadb', 'banco de dados relacional', 'bancos de dados relacionais'],
                'MySQL' => ['mysql', 'mariadb'],
                'PostgreSQL' => ['postgresql', 'postgres'],
                'Power BI' => ['power bi', 'powerbi'],
                'Excel Avançado' => ['excel avancado', 'tabela dinamica', 'tabelas dinamicas', 'vba', 'procv'],
            ],
            Area::Infraestrutura->value => [
                'Docker' => ['docker', 'docker compose', 'containers'],
                'Linux' => ['linux', 'ubuntu', 'debian', 'centos', 'red hat', 'shell script', 'bash'],
                'Windows Server' => ['windows server'],
                'Active Directory' => ['active directory', 'ad ds'],
                'Redes de Computadores' => ['redes de computadores', 'redes', 'tcp/ip', 'cisco', 'roteadores', 'switches'],
                'Cloud (AWS/Azure)' => ['aws', 'azure', 'google cloud', 'gcp', 'cloud', 'computacao em nuvem'],
            ],
            Area::Suporte->value => [
                'Windows' => ['windows', 'windows 10', 'windows 11'],
                'Suporte a Hardware' => ['suporte a hardware', 'manutencao de computadores', 'montagem de computadores', 'hardware', 'help desk', 'helpdesk'],
                'ITIL' => ['itil', 'gestao de servicos de ti'],
            ],
            Area::Seguranca->value => [
                'Segurança da Informação' => ['seguranca da informacao', 'ciberseguranca', 'seguranca cibernetica', 'pentest', 'iso 27001'],
            ],
            Area::Produto->value => [
                'Scrum' => ['scrum', 'kanban', 'metodologias ageis', 'metodologia agil'],
                'UML' => ['uml', 'diagramas de caso de uso'],
                'Levantamento de Requisitos' => ['levantamento de requisitos', 'engenharia de requisitos', 'historias de usuario'],
            ],
            Area::Design->value => [
                'Figma' => ['figma', 'prototipacao', 'ux/ui', 'ui/ux'],
            ],
        ];

        $itens = [];

        foreach ($porArea as $area => $competencias) {
            foreach ($competencias as $nome => $sinonimos) {
                $itens[] = [
                    'nome' => $nome,
                    'categoria' => CategoriaCompetencia::Hard,
                    'area' => Area::from($area),
                    'sinonimos' => $sinonimos,
                ];
            }
        }

        return $itens;
    }

    /**
     * Idiomas são hard skills sem área, com grupo e nível (1 básico, 2 intermediário, 3 avançado).
     *
     * @return list<array{nome: string, categoria: CategoriaCompetencia, area: null, sinonimos: list<string>, grupo: string, nivel: int}>
     */
    private function idiomas(): array
    {
        $idiomas = [
            ['nome' => 'Inglês básico', 'grupo' => 'ingles', 'nivel' => 1, 'sinonimos' => ['ingles basico', 'english basic', 'basic english']],
            ['nome' => 'Inglês intermediário', 'grupo' => 'ingles', 'nivel' => 2, 'sinonimos' => ['ingles intermediario', 'english intermediate', 'intermediate english']],
            ['nome' => 'Inglês avançado', 'grupo' => 'ingles', 'nivel' => 3, 'sinonimos' => ['ingles avancado', 'ingles fluente', 'english advanced', 'fluent english']],
            ['nome' => 'Espanhol básico', 'grupo' => 'espanhol', 'nivel' => 1, 'sinonimos' => ['espanhol basico', 'espanhol']],
        ];

        $itens = [];

        foreach ($idiomas as $idioma) {
            $itens[] = [
                ...$idioma,
                'categoria' => CategoriaCompetencia::Hard,
                'area' => null,
            ];
        }

        return $itens;
    }

    /**
     * @return list<array{nome: string, categoria: CategoriaCompetencia, area: null, sinonimos: list<string>}>
     */
    private function softSkills(): array
    {
        $competencias = [
            'Trabalho em equipe' => ['trabalho em equipe', 'trabalhar em equipe', 'colaboracao', 'colaborativo', 'colaborativa', 'espirito de equipe'],
            'Comunicação' => ['comunicacao', 'comunicativo', 'comunicativa', 'boa comunicacao'],
            'Proatividade' => ['proatividade', 'proativo', 'proativa', 'iniciativa'],
            'Organização' => ['organizacao', 'organizado', 'organizada'],
            'Resolução de problemas' => ['resolucao de problemas', 'solucao de problemas', 'resolver problemas'],
            'Liderança' => ['lideranca', 'lider', 'gestao de equipes'],
            'Atendimento ao público' => ['atendimento ao publico', 'atendimento ao cliente', 'atendimento ao usuario'],
            'Aprendizado contínuo' => ['aprendizado continuo', 'autodidata', 'facilidade de aprendizado', 'vontade de aprender'],
            'Pensamento analítico' => ['pensamento analitico', 'analitico', 'analitica', 'raciocinio logico'],
        ];

        $itens = [];

        foreach ($competencias as $nome => $sinonimos) {
            $itens[] = [
                'nome' => $nome,
                'categoria' => CategoriaCompetencia::Soft,
                'area' => null,
                'sinonimos' => $sinonimos,
            ];
        }

        return $itens;
    }
}
