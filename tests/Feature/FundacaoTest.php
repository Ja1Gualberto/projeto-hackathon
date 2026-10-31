<?php

namespace Tests\Feature;

use App\Enums\Area;
use App\Enums\CategoriaCompetencia;
use App\Enums\EtapaSelecao;
use App\Enums\OrigemCompetencia;
use App\Enums\OrigemPerfilEnergia;
use App\Enums\StatusCandidato;
use App\Enums\TipoCandidato;
use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\MovimentacaoEtapa;
use App\Models\Participacao;
use App\Models\PerfilEnergia;
use App\Models\User;
use App\Models\Vaga;
use Database\Seeders\Support\GeradorPdfCurriculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class FundacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_candidato_recebe_codigo_anonimo_e_data_base_de_retencao(): void
    {
        $candidato = Candidato::factory()->create(['dados_atualizados_em' => null]);

        $this->assertMatchesRegularExpression('/^CAND-\d{4}$/', $candidato->codigo);
        $this->assertNotNull($candidato->dados_atualizados_em);
        $this->assertTrue($candidato->retencao_ate->isSameDay($candidato->dados_atualizados_em->copy()->addYears(2)));
        $this->assertFalse($candidato->retencao_vencida);
        $this->assertSame(StatusCandidato::Ativo, $candidato->status);
    }

    public function test_sinaliza_e_filtra_candidatos_com_retencao_vencida(): void
    {
        Candidato::factory()->create();
        $vencido = Candidato::factory()->retencaoVencida()->create();

        $this->assertTrue($vencido->retencao_vencida);
        $this->assertSame([$vencido->id], Candidato::comRetencaoVencida()->pluck('id')->all());
    }

    public function test_elegiveis_respeitam_o_tipo_da_vaga_e_o_status(): void
    {
        $estagiario = Candidato::factory()->estagiario()->create();
        $servidor = Candidato::factory()->servidor()->create();
        Candidato::factory()->estagiario()->desativado()->create();

        $vagaDeEstagio = Vaga::factory()->paraEstagiarios()->create();
        $vagaParaAmbos = Vaga::factory()->create(['tipo_candidato' => 'ambos']);

        $this->assertSame([$estagiario->id], Candidato::elegiveisPara($vagaDeEstagio)->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$estagiario->id, $servidor->id],
            Candidato::elegiveisPara($vagaParaAmbos)->pluck('id')->all(),
        );
    }

    public function test_campos_pessoais_ficam_fora_da_serializacao(): void
    {
        $dados = Candidato::factory()->create(['cpf' => '52998224725'])->toArray();

        foreach (['nome', 'email', 'telefone', 'cpf', 'data_nascimento', 'links', 'texto_curriculo', 'curriculo_arquivo'] as $campo) {
            $this->assertArrayNotHasKey($campo, $dados);
        }

        $this->assertArrayHasKey('codigo', $dados);
    }

    public function test_excluir_candidato_remove_os_vinculos_em_cascata(): void
    {
        $usuario = User::factory()->create();
        $candidato = Candidato::factory()->create();
        $competencia = Competencia::factory()->create();
        $vaga = Vaga::factory()->create();

        $candidato->competencias()->attach($competencia, ['origem' => OrigemCompetencia::Curriculo->value]);

        $participacao = Participacao::create([
            'vaga_id' => $vaga->id,
            'candidato_id' => $candidato->id,
            'etapa' => EtapaSelecao::Entrevista,
        ]);
        MovimentacaoEtapa::create([
            'participacao_id' => $participacao->id,
            'user_id' => $usuario->id,
            'de' => EtapaSelecao::Triagem,
            'para' => EtapaSelecao::Entrevista,
        ]);

        $anotacao = Anotacao::factory()->create(['candidato_id' => $candidato->id, 'vaga_id' => $vaga->id]);
        $anotacao->competenciasObservadas()->attach($competencia);

        PerfilEnergia::create([
            'candidato_id' => $candidato->id,
            'dinamico_analitico' => 3,
            'individual_equipe' => 4,
            'execucao_planejamento' => 2,
            'justificativa' => 'Justificativa fictícia.',
            'origem' => OrigemPerfilEnergia::Ia,
            'analisado_em' => now(),
        ]);

        $candidato->delete();

        foreach (['candidato_competencia', 'participacoes', 'movimentacoes_etapa', 'anotacoes', 'anotacao_competencia', 'perfis_energia'] as $tabela) {
            $this->assertSame(0, DB::table($tabela)->count(), "A tabela {$tabela} deveria estar vazia.");
        }

        $this->assertModelExists($vaga);
        $this->assertModelExists($competencia);
    }

    public function test_seeders_criam_o_recrutador_e_o_catalogo_de_competencias(): void
    {
        $this->seed();

        $this->assertDatabaseHas('users', ['email' => 'recrutador@setic.demo']);
        $this->assertSame(46, Competencia::count());

        $python = Competencia::where('nome', 'Python')->firstOrFail();
        $this->assertSame(CategoriaCompetencia::Hard, $python->categoria);
        $this->assertSame(Area::Desenvolvimento, $python->area);

        $inglesAvancado = Competencia::where('nome', 'Inglês avançado')->firstOrFail();
        $this->assertTrue($inglesAvancado->ehIdioma());
        $this->assertSame(3, $inglesAvancado->nivel);

        $this->assertSame('c-sharp', Competencia::where('nome', 'C#')->value('slug'));
        $this->assertSame(9, Competencia::where('categoria', CategoriaCompetencia::Soft->value)->count());
    }

    public function test_seeders_podem_rodar_mais_de_uma_vez(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(1, User::where('email', 'recrutador@setic.demo')->count());
        $this->assertSame(46, Competencia::count());
    }

    public function test_gerador_de_pdf_produz_texto_legivel_pelo_parser(): void
    {
        $pdf = (new GeradorPdfCurriculo)->gerar([
            'Ana Lima',
            'FORMAÇÃO',
            'Análise e Desenvolvimento de Sistemas (IFRO)',
            'COMPETÊNCIAS',
            'Python, SQL, Git',
        ]);

        $texto = (new Parser)->parseContent($pdf)->getText();

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('Ana Lima', $texto);
        $this->assertStringContainsString('Análise e Desenvolvimento de Sistemas (IFRO)', $texto);
        $this->assertStringContainsString('Python, SQL, Git', $texto);
    }

    public function test_gerador_de_pdf_quebra_paginas_e_simula_pdf_digitalizado(): void
    {
        $gerador = new GeradorPdfCurriculo;
        $linhas = array_map(fn (int $numero): string => "Linha {$numero}", range(1, 60));

        $documento = (new Parser)->parseContent($gerador->gerar($linhas));
        $this->assertCount(2, $documento->getPages());

        $semTexto = (new Parser)->parseContent($gerador->gerarSemTexto())->getText();
        $this->assertSame('', trim($semTexto));
    }

    public function test_paginas_de_erro_sao_amigaveis(): void
    {
        $this->get('/pagina-que-nao-existe')
            ->assertNotFound()
            ->assertSee('Página não encontrada');
    }

    public function test_badge_de_tipo_mostra_texto_alem_da_cor(): void
    {
        $html = $this->blade('<x-badge-tipo :tipo="$tipo" />', ['tipo' => TipoCandidato::Estagiario]);

        $html->assertSee('Estagiário');
    }
}
