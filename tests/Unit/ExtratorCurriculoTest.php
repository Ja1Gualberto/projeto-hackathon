<?php

namespace Tests\Unit;

use App\DTOs\DadosExtraidosDTO;
use App\Enums\Area;
use App\Services\Curriculo\ExtratorCurriculo;
use Database\Seeders\CompetenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ExtratorCurriculoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CompetenciaSeeder::class);
    }

    private function extrair(string $texto): DadosExtraidosDTO
    {
        return app(ExtratorCurriculo::class)->extrair($texto);
    }

    /** @return list<string> */
    private function nomes(DadosExtraidosDTO $dados): array
    {
        return array_column($dados->competencias, 'nome');
    }

    public function test_reconhece_competencias_listadas(): void
    {
        $dados = $this->extrair('Conhecimentos: Python, SQL, Git e Docker');

        foreach (['Python', 'SQL', 'Git', 'Docker'] as $esperada) {
            $this->assertContains($esperada, $this->nomes($dados));
        }
    }

    public function test_respeita_fronteiras_de_termos_com_simbolos(): void
    {
        $nomes = $this->nomes($this->extrair('Experiência com C#, .NET e Node.js'));

        $this->assertContains('C#', $nomes);
        $this->assertContains('.NET', $nomes);
        $this->assertContains('Node.js', $nomes);
        $this->assertNotContains('Java', $nomes);
        $this->assertNotContains('JavaScript', $nomes);
    }

    public function test_postgresql_implica_sql(): void
    {
        $nomes = $this->nomes($this->extrair('Banco de dados PostgreSQL'));

        $this->assertContains('PostgreSQL', $nomes);
        $this->assertContains('SQL', $nomes);
    }

    public function test_idioma_usa_o_nivel_informado_na_mesma_linha(): void
    {
        $nomes = $this->nomes($this->extrair('Inglês: intermediário'));

        $this->assertContains('Inglês intermediário', $nomes);
        $this->assertNotContains('Inglês básico', $nomes);
        $this->assertNotContains('Inglês avançado', $nomes);
    }

    #[TestWith(['Inglês'])]
    #[TestWith(["IDIOMAS\nInglês"])]
    public function test_idioma_sem_nivel_vira_nivel_basico(string $texto): void
    {
        $this->assertContains('Inglês básico', $this->nomes($this->extrair($texto)));
    }

    #[TestWith(['Inglês fluente', 'Inglês avançado'])]
    #[TestWith(['English - advanced', 'Inglês avançado'])]
    #[TestWith(['Inglês (básico)', 'Inglês básico'])]
    public function test_variacoes_de_nivel_de_idioma(string $texto, string $esperado): void
    {
        $this->assertContains($esperado, $this->nomes($this->extrair($texto)));
    }

    public function test_soft_skills_sao_reconhecidas_como_comportamentais(): void
    {
        $dados = $this->extrair('Trabalho em equipe e comunicação');
        $soft = array_filter($dados->competencias, fn (array $item): bool => $item['categoria'] === 'soft');

        $this->assertEqualsCanonicalizing(['Trabalho em equipe', 'Comunicação'], array_column($soft, 'nome'));
    }

    public function test_termos_fora_do_catalogo_viram_sugestoes_e_nao_competencias(): void
    {
        $dados = $this->extrair("COMPETÊNCIAS\nKubernetes, Terraform, Python");

        $this->assertSame(['Kubernetes', 'Terraform'], $dados->sugestoes_competencias);
        $this->assertContains('Python', $this->nomes($dados));
        $this->assertNotContains('Kubernetes', $this->nomes($dados));
    }

    public function test_sugestoes_ignoram_termos_que_ja_estao_no_catalogo_e_repeticoes(): void
    {
        $dados = $this->extrair('Habilidades: Kubernetes; kubernetes; SQL Server; Comunicação • Terraform');

        $this->assertSame(['Kubernetes', 'Terraform'], $dados->sugestoes_competencias);
    }

    #[TestWith(['CPF 123.456.789-00', null])]
    #[TestWith(['CPF: 111.111.111-11', null])]
    #[TestWith(['CPF: 529.982.247-25', '52998224725'])]
    #[TestWith(['cpf 52998224725', '52998224725'])]
    public function test_cpf_so_e_aceito_com_digitos_verificadores_validos(string $texto, ?string $esperado): void
    {
        $this->assertSame($esperado, $this->extrair($texto)->cpf);
    }

    public function test_extrai_identificacao_contato_data_e_links(): void
    {
        $dados = $this->extrair(implode("\n", [
            'Ana Lima',
            'ana.lima@exemplo.com.br | (69) 99876-5432',
            'Data de nascimento: 14/03/2004',
            'github.com/analima',
            'Portfólio: https://linkedin.com/in/ana-lima.',
            'https://github.com/analima',
        ]));

        $this->assertSame('Ana Lima', $dados->nome);
        $this->assertSame('ana.lima@exemplo.com.br', $dados->email);
        $this->assertSame('(69) 99876-5432', $dados->telefone);
        $this->assertSame('2004-03-14', $dados->data_nascimento);
        $this->assertSame(['https://github.com/analima', 'https://linkedin.com/in/ana-lima'], $dados->links);
    }

    #[TestWith(['Telefone: +55 69 3216-5000', '(69) 3216-5000'])]
    #[TestWith(['Celular 69 99999-0000', '(69) 99999-0000'])]
    public function test_normaliza_telefones_brasileiros(string $texto, string $esperado): void
    {
        $this->assertSame($esperado, $this->extrair($texto)->telefone);
    }

    public function test_nome_em_caixa_alta_e_normalizado_e_titulos_nao_viram_nome(): void
    {
        $this->assertSame('Mariana Souza', $this->extrair("CURRÍCULO\nMARIANA SOUZA\nmariana@exemplo.com.br")->nome);
        $this->assertNull($this->extrair("FORMAÇÃO\nSistemas de Informação")->nome);
    }

    public function test_separa_secoes_e_remove_marcadores(): void
    {
        $dados = $this->extrair(implode("\n", [
            'Bruno Teixeira',
            'FORMAÇÃO ACADÊMICA',
            '• Análise e Desenvolvimento de Sistemas — Instituto Vale Digital',
            'Cursos complementares',
            '- Introdução a Python (40h)',
            'EXPERIÊNCIA PROFISSIONAL',
            '- Estágio em suporte de sistemas, com Python e SQL',
            'Projetos',
            '* Painel de chamados em Laravel',
            'https://github.com/bruno-demo',
        ]));

        $this->assertSame([
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital',
            'Introdução a Python (40h)',
        ], $dados->formacoes);
        $this->assertSame(['Estágio em suporte de sistemas, com Python e SQL'], $dados->experiencias);
        $this->assertSame(['Painel de chamados em Laravel'], $dados->projetos);
        $this->assertSame(['https://github.com/bruno-demo'], $dados->links);
    }

    public function test_nome_em_duas_linhas_e_unido_e_cargo_nao_vira_nome(): void
    {
        $this->assertSame('Maria Clara Souza', $this->extrair("Maria\nClara Souza\nTécnica em Informática\nContato")->nome);
        $this->assertNull($this->extrair("Técnico em Informática\nDesenvolvedor Full Stack")->nome);
        $this->assertSame('Ana Lima', $this->extrair("Técnica em Informática\nAna Lima")->nome);
    }

    public function test_destaque_valido_tem_prioridade_e_invalido_e_ignorado(): void
    {
        $extrator = app(ExtratorCurriculo::class);
        $texto = "Maria Clara\nEducação\nInstituto Vale Digital (fictício)";

        $this->assertSame('Maria Clara Souza', $extrator->extrair($texto, 'MARIA CLARA SOUZA')->nome);
        $this->assertSame('Maria Clara', $extrator->extrair($texto, 'Educação')->nome);
        $this->assertSame('Maria Clara', $extrator->extrair($texto, 'Desenvolvedora Full Stack')->nome);
    }

    #[TestWith(['(69) 9 9988-7766', '(69) 99988-7766'])]
    #[TestWith(['+55 (69) 98877-6655', '(69) 98877-6655'])]
    #[TestWith(['69 9.9988.7766', '(69) 99988-7766'])]
    public function test_telefone_com_nono_digito_separado(string $texto, string $esperado): void
    {
        $this->assertSame($esperado, $this->extrair($texto)->telefone);
    }

    public function test_periodo_nao_e_confundido_com_telefone(): void
    {
        $this->assertNull($this->extrair("2022 - 2027\n03/2024 - 06/2024, Porto Velho\n2024 - 2025 | Sistemas")->telefone);
    }

    #[TestWith(['Educação'])]
    #[TestWith(['FORMAÇÃO ACADÊMICA'])]
    #[TestWith(['Histórico Acadêmico'])]
    public function test_variacoes_do_titulo_de_formacao(string $titulo): void
    {
        $dados = $this->extrair("Ana Lima\n{$titulo}\nInstituto Vale Digital (fictício)");

        $this->assertSame(['Instituto Vale Digital (fictício)'], $dados->formacoes);
    }

    #[TestWith(['Experiência auxiliando alunos'])]
    #[TestWith(['Experiência em Python'])]
    #[TestWith(['Formação de equipes'])]
    public function test_frase_que_comeca_como_titulo_nao_muda_de_secao(string $frase): void
    {
        $dados = $this->extrair("Ana Lima\nHabilidades\n{$frase}\nEducação\nInstituto Vale Digital (fictício)");

        $this->assertSame([], $dados->experiencias);
        $this->assertSame(['Instituto Vale Digital (fictício)'], $dados->formacoes);
    }

    public function test_titulos_de_competencias_em_varias_formas_nao_viram_sugestao(): void
    {
        $dados = $this->extrair(implode("\n", [
            'COMPETÊNCIAS', 'LINGUAGENS E TECS', 'Terraform', 'FRAMEWORKS E BIBLIOTECAS', 'Kubernetes', 'BANCO DE DADOS E', 'FERRAMENTAS', 'Ansible', 'CONHECIMENTOS GERAIS', 'Zabbix',
        ]));

        $this->assertSame(['Terraform', 'Kubernetes', 'Ansible', 'Zabbix'], $dados->sugestoes_competencias);
    }

    public function test_projeto_rotulado_dentro_da_experiencia_vai_para_projetos_e_a_leitura_volta(): void
    {
        $dados = $this->extrair(implode("\n", [
            'Ana Lima',
            'EXPERIÊNCIAS',
            'Estagiária de desenvolvimento',
            'Projetos: Colaboração no sistema de escalas, atuando na construção de rotinas',
            'para controle de horas de trabalho.',
            'Monitora de laboratório',
            'Projeto: “Sistema de Agendamento',
            'de Salas”',
            'Aplicação web para reservar salas.',
            'Tutora de programação',
        ]));

        $this->assertSame([
            'Colaboração no sistema de escalas, atuando na construção de rotinas para controle de horas de trabalho.',
            '“Sistema de Agendamento de Salas”',
            'Aplicação web para reservar salas.',
        ], $dados->projetos);
        $this->assertSame(['Estagiária de desenvolvimento', 'Monitora de laboratório', 'Tutora de programação'], $dados->experiencias);
    }

    public function test_certificados_vao_para_experiencias_e_cursos_continuam_em_formacao(): void
    {
        $dados = $this->extrair(implode("\n", [
            'Ana Lima',
            'Experiência',
            'Estágio no Laboratório Horizonte',
            'Certificados',
            'Curso de Extensão em Algoritmos e',
            'Estrutura de Dados',
            'Duração do Curso: 30 horas',
            'Git e GitHub na Prática',
            'Duração do Curso: 10 horas',
            'Cursos complementares',
            'Introdução a Python',
        ]));

        $this->assertSame([
            'Estágio no Laboratório Horizonte',
            'Curso de Extensão em Algoritmos e Estrutura de Dados — Duração do Curso: 30 horas',
            'Git e GitHub na Prática — Duração do Curso: 10 horas',
        ], $dados->experiencias);
        $this->assertSame(['Introdução a Python'], $dados->formacoes);
    }

    public function test_paragrafo_quebrado_vira_um_item_e_itens_de_lista_continuam_separados(): void
    {
        $dados = $this->extrair(implode("\n", [
            'EXPERIÊNCIAS',
            'Atuei como estagiário em informática na coordenação do Instituto',
            'Federal, entre março e junho de',
            '2024, em Porto Velho. Durante o período, participei da instalação',
            'de equipamentos.',
            'Criação de interfaces responsivas com CSS, implementando',
            'interatividade com JavaScript e',
            'Ajax',
            'Gerenciamento de código com Git',
        ]));

        $this->assertSame([
            'Atuei como estagiário em informática na coordenação do Instituto Federal, entre março e junho de 2024, em Porto Velho. Durante o período, participei da instalação de equipamentos.',
            'Criação de interfaces responsivas com CSS, implementando interatividade com JavaScript e Ajax',
            'Gerenciamento de código com Git',
        ], $dados->experiencias);
    }

    public function test_cursando_nao_junta_a_linha_seguinte_na_formacao(): void
    {
        $dados = $this->extrair(implode("\n", [
            'Educação',
            'Instituto Vale Digital (fictício) 2022 - 2027',
            'Graduação em Análise e Desenvolvimento de Sistemas - Cursando',
            'Técnico em Informática - Concluído',
        ]));

        $this->assertSame([
            'Instituto Vale Digital (fictício) 2022 - 2027',
            'Graduação em Análise e Desenvolvimento de Sistemas - Cursando',
            'Técnico em Informática - Concluído',
        ], $dados->formacoes);
    }

    public function test_data_solta_vai_para_o_item_mesmo_no_meio_de_um_titulo_quebrado(): void
    {
        $dados = $this->extrair(implode("\n", [
            'Experiência',
            'Estágio na Coordenação de Gestão de',
            '2024',
            'Tecnologia da Informação',
            '03/2024 - 06/2024, Porto Velho',
        ]));

        $this->assertSame([
            'Estágio na Coordenação de Gestão de Tecnologia da Informação — 2024',
            '03/2024 - 06/2024, Porto Velho',
        ], $dados->experiencias);
    }

    public function test_item_longo_e_dividido_em_frases_sem_perder_conteudo(): void
    {
        $frases = [
            'Primeira frase do parágrafo '.str_repeat('com bastante conteúdo ', 8).'até o fim.',
            'Segunda frase do parágrafo '.str_repeat('com outro conteúdo ', 9).'até o fim.',
            'Terceira frase bem curta.',
        ];

        $dados = $this->extrair("EXPERIÊNCIAS\n".implode(' ', $frases));

        foreach ($dados->experiencias as $item) {
            $this->assertLessThanOrEqual(300, mb_strlen($item));
        }

        $this->assertGreaterThan(1, count($dados->experiencias));
        $this->assertSame(implode(' ', $frases), implode(' ', $dados->experiencias));
    }

    public function test_area_sugerida_e_experiencia_na_area(): void
    {
        $comExperiencia = $this->extrair(implode("\n", [
            'COMPETÊNCIAS',
            'Python, Git, Laravel, SQL',
            'EXPERIÊNCIA',
            'Desenvolvimento de APIs com Laravel no Laboratório Horizonte',
        ]));

        $this->assertSame(Area::Desenvolvimento->value, $comExperiencia->area_sugerida);
        $this->assertTrue($comExperiencia->experiencia_na_area);

        $semExperiencia = $this->extrair("COMPETÊNCIAS\nPower BI, SQL, Excel avançado\nEXPERIÊNCIA\nAtendimento em loja");

        $this->assertSame(Area::Dados->value, $semExperiencia->area_sugerida);
        $this->assertFalse($semExperiencia->experiencia_na_area);
    }

    public function test_guarda_o_trecho_de_origem_de_cada_competencia(): void
    {
        $dados = $this->extrair("Ana Lima\nEXPERIÊNCIA\nAutomação de relatórios em Python para a secretaria fictícia");
        $python = collect($dados->competencias)->firstWhere('nome', 'Python');

        $this->assertSame('Automação de relatórios em Python para a secretaria fictícia', $python['trecho']);
        $this->assertIsInt($python['id']);
    }

    public function test_texto_de_referencia_identifica_ao_menos_oitenta_por_cento_do_catalogo(): void
    {
        $esperadas = ['Python', 'SQL', 'Git', 'Docker', 'Linux', 'Laravel', 'Power BI', 'Scrum', 'Trabalho em equipe', 'Inglês intermediário'];
        $texto = <<<'TXT'
            Carla Menezes
            carla.menezes@exemplo.com.br
            RESUMO
            Estudante de Sistemas de Informação, com projetos pessoais em python e versionamento no GitHub.
            EXPERIÊNCIA PROFISSIONAL
            Estágio no Laboratório Horizonte (fictício): containers Docker em servidores Ubuntu,
            APIs em Laravel e consultas em MySQL. Rotinas ágeis com Scrum.
            Dashboards de indicadores no PowerBI.
            Gosto de trabalhar em equipe.
            IDIOMAS
            Inglês - nível intermediário
            TXT;

        $encontradas = array_intersect($esperadas, $this->nomes($this->extrair($texto)));

        $this->assertGreaterThanOrEqual(8, count($encontradas), 'Encontradas: '.implode(', ', $encontradas));
    }

    public function test_texto_vazio_devolve_dados_vazios(): void
    {
        $this->assertEquals(DadosExtraidosDTO::vazio(), $this->extrair("  \n\t "));
    }

    public function test_itens_longos_sao_limitados_ao_tamanho_aceito_na_revisao(): void
    {
        $dados = $this->extrair("FORMAÇÃO\n".str_repeat('Sistemas de Informação ', 30));

        $this->assertLessThanOrEqual(300, mb_strlen($dados->formacoes[0]));
    }
}
