<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Participacao;
use App\Models\User;
use App\Models\Vaga;
use Database\Seeders\CompetenciaSeeder;
use Database\Seeders\Support\GeradorPdfCurriculo;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class VinculoCandidatoVagaTest extends TestCase
{
    use RefreshDatabase;

    public function test_quadro_comeca_vazio_e_nao_inclui_candidatos_sem_vinculo(): void
    {
        $vaga = Vaga::factory()->create(['tipo_candidato' => null]);
        Candidato::factory()->create(['tipo' => null]);

        $this->actingAs(User::factory()->create())->getJson(route('vagas.show', $vaga))
            ->assertOk()->assertJsonCount(0, 'data.quadro-vaga.colunas.triagem')
            ->assertJsonPath('data.quadro-vaga.totalElegiveis', 0);
    }

    public function test_criacao_de_processo_nao_exige_classificacao_de_candidato(): void
    {
        $competencia = Competencia::factory()->hard()->create();

        $this->actingAs(User::factory()->create())->post(route('vagas.store'), [
            'titulo' => 'Desenvolvimento de sistemas', 'area' => 'desenvolvimento',
            'competencias' => [$competencia->id => ['peso' => 3]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $vaga = Vaga::query()->sole();
        $this->assertNull($vaga->tipo_candidato);
        $this->get(route('vagas.edit', $vaga))->assertOk()->assertDontSee('name="tipo_candidato"', false);
    }

    public function test_importacao_aberta_pela_vaga_ja_seleciona_o_processo_e_nao_lista_encerrados(): void
    {
        $vaga = Vaga::factory()->create(['titulo' => 'Processo de desenvolvimento']);
        $encerrada = Vaga::factory()->encerrada()->create(['titulo' => 'Processo encerrado de suporte']);

        $this->actingAs(User::factory()->create())->get(route('importacoes.create', ['vaga_id' => $vaga->id]))
            ->assertOk()->assertSee('value="'.$vaga->id.'" selected', false)->assertSee($vaga->titulo)->assertDontSee($encerrada->titulo);
    }

    public function test_selecao_manual_vincula_a_varias_vagas_sem_duplicar_cadastro(): void
    {
        $candidato = Candidato::factory()->create(['tipo' => null]);
        $vagas = Vaga::factory()->count(2)->create(['tipo_candidato' => null]);
        $this->actingAs(User::factory()->create());

        foreach ($vagas as $vaga) {
            $this->post(route('vagas.triagem.store', $vaga), ['candidatos' => [$candidato->id]])
                ->assertRedirect(route('vagas.show', $vaga))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('participacoes', ['vaga_id' => $vaga->id, 'candidato_id' => $candidato->id, 'etapa' => 'triagem']);
            $this->getJson(route('vagas.show', $vaga))->assertOk()
                ->assertJsonPath('data.quadro-vaga.colunas.triagem.0.codigo', $candidato->codigo)
                ->assertJsonPath('data.quadro-vaga.colunas.triagem.0.nome', null);
        }

        $this->assertDatabaseCount('candidatos', 1);
        $this->assertDatabaseCount('participacoes', 2);
        $this->assertDatabaseCount('movimentacoes_etapa', 0);
    }

    public function test_selecionar_novamente_nao_reinicia_etapa_nem_duplica_vinculo(): void
    {
        $vaga = Vaga::factory()->create();
        $candidato = Candidato::factory()->create();
        Participacao::query()->create(['vaga_id' => $vaga->id, 'candidato_id' => $candidato->id, 'etapa' => 'entrevista']);

        $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), ['candidatos' => [$candidato->id]])
            ->assertRedirect(route('vagas.show', $vaga))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('participacoes', 1);
        $this->assertDatabaseHas('participacoes', ['vaga_id' => $vaga->id, 'candidato_id' => $candidato->id, 'etapa' => 'entrevista']);
    }

    public function test_selecionar_todos_sem_filtros_inclui_todas_as_paginas_e_mais_de_cem_candidatos(): void
    {
        $vaga = Vaga::factory()->create();
        Candidato::factory()->count(101)->create();
        Candidato::factory()->desativado()->create();

        $resposta = $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), [
            'todos_filtrados' => '1',
        ]);

        $resposta->assertRedirect(route('vagas.show', $vaga))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('participacoes', 101);
    }

    public function test_selecionar_todos_respeita_busca_competencias_e_exclusoes_em_todas_as_paginas(): void
    {
        $vaga = Vaga::factory()->create();
        $competencias = Competencia::factory()->count(2)->create();
        $encontrados = Candidato::factory()->count(13)
            ->sequence(fn (Sequence $sequencia): array => ['codigo' => 'CAND-'.(1000 + $sequencia->index)])
            ->create(['nome' => 'Ana Lima']);
        foreach ($encontrados as $candidato) {
            $candidato->competencias()->attach($competencias->last(), ['origem' => 'curriculo']);
        }
        $outroCodigo = Candidato::factory()->create(['codigo' => 'CAND-2000']);
        $outroCodigo->competencias()->attach($competencias->modelKeys(), ['origem' => 'curriculo']);
        Candidato::factory()->create(['codigo' => 'CAND-1030']);
        $vinculado = Candidato::factory()->create(['codigo' => 'CAND-1040']);
        $vinculado->competencias()->attach($competencias->last(), ['origem' => 'curriculo']);
        Participacao::query()->create(['vaga_id' => $vaga->id, 'candidato_id' => $vinculado->id, 'etapa' => 'entrevista']);

        $resposta = $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), [
            'todos_filtrados' => '1', 'busca' => 'CAND-10', 'competencias' => $competencias->modelKeys(),
            'excluidos' => [$encontrados->first()->id],
        ]);

        $resposta->assertRedirect(route('vagas.show', $vaga))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('participacoes', 13);
        $this->assertDatabaseMissing('participacoes', ['vaga_id' => $vaga->id, 'candidato_id' => $encontrados->first()->id]);
        $this->assertDatabaseHas('participacoes', ['vaga_id' => $vaga->id, 'candidato_id' => $encontrados->last()->id, 'etapa' => 'triagem']);
        $this->assertDatabaseHas('participacoes', ['vaga_id' => $vaga->id, 'candidato_id' => $vinculado->id, 'etapa' => 'entrevista']);
    }

    public function test_selecionar_todos_respeita_equivalencia_de_idiomas(): void
    {
        $vaga = Vaga::factory()->create();
        $basico = Competencia::factory()->idioma('ingles', 1)->create();
        $avancado = Competencia::factory()->idioma('ingles', 3)->create();
        $candidato = Candidato::factory()->create();
        $candidato->competencias()->attach($avancado, ['origem' => 'curriculo']);

        $resposta = $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), [
            'todos_filtrados' => '1', 'competencias' => [$basico->id],
        ]);

        $resposta->assertRedirect(route('vagas.show', $vaga))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('participacoes', ['vaga_id' => $vaga->id, 'candidato_id' => $candidato->id, 'etapa' => 'triagem']);
    }

    public function test_desmarcar_todos_os_resultados_recusa_uma_selecao_vazia(): void
    {
        $vaga = Vaga::factory()->create();
        $candidato = Candidato::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), [
            'todos_filtrados' => '1', 'excluidos' => [$candidato->id],
        ]);

        $resposta->assertSessionHasErrors(['candidatos' => 'Selecione ao menos um candidato para adicionar à triagem.']);
        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_selecionar_todos_valida_os_filtros_antes_de_vincular(): void
    {
        $vaga = Vaga::factory()->create();
        Candidato::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), [
            'todos_filtrados' => '1', 'competencias' => ['invalida'],
        ]);

        $resposta->assertSessionHasErrors('competencias.0');
        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_botao_selecionar_todos_fica_acima_dos_cards_com_divisor(): void
    {
        $vaga = Vaga::factory()->create();
        $candidato = Candidato::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', $vaga));

        $resposta->assertOk()->assertSee('data-busca-candidatos', false)->assertSee('data-limpar-busca', false)
            ->assertSeeInOrder(['Selecionar todos', '<hr', $candidato->codigo], false);
    }

    public function test_filtro_ajax_da_triagem_retorna_somente_resultados_anonimos_e_filtros_da_selecao(): void
    {
        $vaga = Vaga::factory()->create();
        $competencia = Competencia::factory()->hard()->create();
        $encontrado = Candidato::factory()->create(['codigo' => 'CAND-4001', 'nome' => 'Ana Lima']);
        $encontrado->competencias()->attach($competencia, ['origem' => 'curriculo']);
        $outro = Candidato::factory()->create(['nome' => 'Bruna Silva']);
        $outro->competencias()->attach($competencia, ['origem' => 'curriculo']);

        $resposta = $this->actingAs(User::factory()->create())->getJson(route('vagas.triagem.index', [
            $vaga, 'busca' => 'CAND-4001', 'competencias' => [$competencia->id],
        ]));

        $resposta->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure(['message', 'fragments' => ['resultados-candidatos'], 'redirect'])
            ->assertJsonCount(1, 'fragments')->assertJsonPath('message', null)->assertJsonPath('redirect', null);
        $html = $resposta->json('fragments.resultados-candidatos');
        $this->assertStringContainsString($encontrado->codigo, $html);
        $this->assertStringContainsString('1 candidatos disponíveis', $html);
        $this->assertStringContainsString('name="busca" value="CAND-4001"', $html);
        $this->assertStringContainsString('name="competencias[]" value="'.$competencia->id.'"', $html);
        $this->assertStringContainsString('name="todos_filtrados" value="0"', $html);
        $this->assertStringNotContainsString($outro->codigo, $html);
        $this->assertStringNotContainsString($encontrado->nome, $html);
        $this->assertStringNotContainsString($encontrado->email, $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('Filtrar por competências', $html);
    }

    public function test_paginacao_ajax_da_triagem_preserva_filtros_e_o_total_da_selecao_global(): void
    {
        $vaga = Vaga::factory()->create();
        $competencia = Competencia::factory()->hard()->create();
        $candidatos = Candidato::factory()->count(13)
            ->sequence(fn (Sequence $sequencia): array => ['codigo' => 'CAND-'.(3000 + $sequencia->index)])
            ->create(['nome' => 'Ana Lima']);
        foreach ($candidatos as $candidato) {
            $candidato->competencias()->attach($competencia, ['origem' => 'curriculo']);
        }

        $resposta = $this->actingAs(User::factory()->create())->getJson(route('vagas.triagem.index', [
            $vaga, 'busca' => 'CAND-30', 'competencias' => [$competencia->id], 'page' => 2,
        ]));

        $resposta->assertOk();
        $html = $resposta->json('fragments.resultados-candidatos');
        $this->assertStringContainsString($candidatos->last()->codigo, $html);
        $this->assertStringNotContainsString($candidatos->first()->codigo, $html);
        $this->assertStringContainsString('data-total-candidatos="13"', $html);
        $this->assertStringContainsString('data-paginacao-busca', $html);
        $this->assertStringContainsString('competencias%5B0%5D='.$competencia->id, $html);
        $this->assertStringContainsString('busca=CAND-30', $html);
    }

    public function test_limpar_filtros_por_ajax_inclui_candidatos_sem_competencias(): void
    {
        $vaga = Vaga::factory()->create();
        $competencia = Competencia::factory()->hard()->create();
        $vaga->competencias()->attach($competencia, ['peso' => 3]);
        $candidato = Candidato::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->getJson(route('vagas.triagem.index', [$vaga, 'busca' => '']));

        $resposta->assertOk();
        $html = $resposta->json('fragments.resultados-candidatos');
        $this->assertStringContainsString($candidato->codigo, $html);
        $this->assertStringNotContainsString('name="competencias[]"', $html);
    }

    public function test_filtro_ajax_invalido_retorna_422_sem_fragmentos(): void
    {
        $vaga = Vaga::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->getJson(route('vagas.triagem.index', [
            $vaga, 'competencias' => ['invalida'],
        ]));

        $resposta->assertUnprocessable()->assertJsonValidationErrors('competencias.0')->assertJsonMissingPath('fragments');
    }

    public function test_selecao_de_candidatos_abre_com_as_competencias_da_vaga_marcadas(): void
    {
        $vaga = Vaga::factory()->create();
        $tecnica = Competencia::factory()->hard()->create();
        $comportamental = Competencia::factory()->soft()->create();
        $outra = Competencia::factory()->hard()->create();
        $vaga->competencias()->attach([$tecnica->id, $comportamental->id], ['peso' => 3]);

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', $vaga));

        $resposta->assertOk()
            ->assertSee('name="competencias[]" value="'.$tecnica->id.'" checked', false)
            ->assertSee('name="competencias[]" value="'.$comportamental->id.'" checked', false)
            ->assertSee($outra->nome)
            ->assertDontSee('name="competencias[]" value="'.$outra->id.'" checked', false)
            ->assertSee('href="'.route('vagas.triagem.index', [$vaga, 'busca' => '']).'"', false);
    }

    public function test_selecao_de_candidatos_preserva_as_competencias_escolhidas_pelo_usuario(): void
    {
        $vaga = Vaga::factory()->create();
        $daVaga = Competencia::factory()->hard()->create();
        $escolhida = Competencia::factory()->soft()->create();
        $vaga->competencias()->attach($daVaga, ['peso' => 3]);

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', [
            $vaga, 'competencias' => [$escolhida->id],
        ]));

        $resposta->assertOk()
            ->assertSee('name="competencias[]" value="'.$escolhida->id.'" checked', false)
            ->assertDontSee('name="competencias[]" value="'.$daVaga->id.'" checked', false);
    }

    public function test_selecao_de_candidatos_permite_filtrar_sem_competencias_e_limpar_filtros(): void
    {
        $vaga = Vaga::factory()->create();
        $competencia = Competencia::factory()->hard()->create();
        $vaga->competencias()->attach($competencia, ['peso' => 3]);
        $semCompetencias = Candidato::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', [$vaga, 'busca' => '']));

        $resposta->assertOk()
            ->assertSee($competencia->nome)
            ->assertDontSee('name="competencias[]" value="'.$competencia->id.'" checked', false)
            ->assertSee($semCompetencias->codigo);
    }

    public function test_selecao_de_candidatos_sem_competencias_na_vaga_abre_sem_marcacoes(): void
    {
        $vaga = Vaga::factory()->create();
        $competencia = Competencia::factory()->hard()->create();

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', $vaga));

        $resposta->assertOk()
            ->assertSee($competencia->nome)
            ->assertDontSee('name="competencias[]" value="'.$competencia->id.'" checked', false);
    }

    public function test_busca_para_triagem_aceita_uma_competencia_e_exclui_inativos_e_ja_vinculados(): void
    {
        $vaga = Vaga::factory()->create();
        $python = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $sql = Competencia::factory()->hard()->create(['nome' => 'SQL']);
        $selecionavel = Candidato::factory()->create();
        $vinculado = Candidato::factory()->create();
        $inativo = Candidato::factory()->desativado()->create();
        foreach ([$selecionavel, $vinculado, $inativo] as $candidato) {
            $candidato->competencias()->attach([$python->id, $sql->id], ['origem' => 'curriculo']);
        }
        $parcial = Candidato::factory()->create();
        $parcial->competencias()->attach($python, ['origem' => 'curriculo']);
        $semCorrespondencia = Candidato::factory()->create();
        Participacao::query()->create(['vaga_id' => $vaga->id, 'candidato_id' => $vinculado->id, 'etapa' => 'triagem']);

        $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', [$vaga, 'competencias' => [$python->id, $sql->id]]))
            ->assertOk()->assertSee($selecionavel->codigo)->assertDontSee($selecionavel->nome)
            ->assertSeeInOrder([$selecionavel->codigo, $parcial->codigo])
            ->assertDontSee($vinculado->codigo)->assertDontSee($inativo->codigo)->assertDontSee($semCorrespondencia->codigo);
    }

    public function test_triagem_ordena_pela_quantidade_de_competencias_selecionadas_e_nao_pelos_pesos(): void
    {
        $vaga = Vaga::factory()->create();
        $competencias = Competencia::factory()->count(4)->create();
        $vaga->competencias()->attach($competencias->modelKeys(), ['peso' => 1]);
        $vaga->competencias()->updateExistingPivot($competencias->first()->id, ['peso' => 5]);
        $uma = Candidato::factory()->create(['nome' => 'Ana']);
        $duas = Candidato::factory()->create(['nome' => 'Bruna']);
        $tres = Candidato::factory()->create(['nome' => 'Carla']);
        $quatro = Candidato::factory()->create(['nome' => 'Zuleica']);
        $uma->competencias()->attach($competencias->first(), ['origem' => 'curriculo']);
        $duas->competencias()->attach($competencias->slice(1, 2)->modelKeys(), ['origem' => 'curriculo']);
        $tres->competencias()->attach($competencias->slice(1)->modelKeys(), ['origem' => 'curriculo']);
        $quatro->competencias()->attach($competencias->modelKeys(), ['origem' => 'curriculo']);
        $extras = Competencia::factory()->count(4)->create();
        $uma->competencias()->attach($extras->modelKeys(), ['origem' => 'curriculo']);

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', [
            $vaga, 'competencias' => $competencias->modelKeys(),
        ]));

        $resposta->assertOk()->assertSeeInOrder([$quatro->codigo, $tres->codigo, $duas->codigo, $uma->codigo])
            ->assertSee('pelo menos uma das competências marcadas');
    }

    public function test_triagem_conta_cada_competencia_selecionada_uma_vez_mesmo_com_idiomas_equivalentes(): void
    {
        $vaga = Vaga::factory()->create();
        $basico = Competencia::factory()->idioma('ingles', 1)->create();
        $avancado = Competencia::factory()->idioma('ingles', 3)->create();
        $python = Competencia::factory()->hard()->create();
        $duas = Candidato::factory()->create(['nome' => 'Zuleica']);
        $duas->competencias()->attach([$avancado->id, $python->id], ['origem' => 'curriculo']);
        $uma = Candidato::factory()->create(['nome' => 'Ana']);
        $uma->competencias()->attach([$basico->id, $avancado->id], ['origem' => 'curriculo']);

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', [
            $vaga, 'competencias' => [$basico->id, $python->id],
        ]));

        $resposta->assertOk()->assertSeeInOrder([$duas->codigo, $uma->codigo]);
    }

    public function test_triagem_ordena_antes_de_paginar_e_mantem_desempate_e_filtros(): void
    {
        $vaga = Vaga::factory()->create();
        $competencias = Competencia::factory()->count(2)->create();
        $parciais = Candidato::factory()->count(12)->create(['nome' => 'Ana']);
        foreach ($parciais as $candidato) {
            $candidato->competencias()->attach($competencias->first(), ['origem' => 'curriculo']);
        }
        $completo = Candidato::factory()->create(['nome' => 'Zuleica']);
        $completo->competencias()->attach($competencias->modelKeys(), ['origem' => 'curriculo']);
        $this->actingAs(User::factory()->create());
        $filtros = [$vaga, 'competencias' => $competencias->modelKeys()];

        $primeira = $this->get(route('vagas.triagem.index', $filtros));
        $segunda = $this->get(route('vagas.triagem.index', [...$filtros, 'page' => 2]));

        $primeira->assertOk()->assertSee('13 candidatos disponíveis')
            ->assertSeeInOrder([$completo->codigo, ...$parciais->take(11)->pluck('codigo')->all()])
            ->assertDontSee($parciais->last()->codigo)
            ->assertSee('competencias%5B0%5D='.$competencias->first()->id, false);
        $segunda->assertOk()->assertSee($parciais->last()->codigo)->assertDontSee($completo->codigo);
    }

    public function test_busca_textual_restringe_os_candidatos_mesmo_quando_alguma_competencia_corresponde(): void
    {
        $vaga = Vaga::factory()->create();
        $competencias = Competencia::factory()->count(2)->create();
        $encontrado = Candidato::factory()->create(['codigo' => 'CAND-6001']);
        $encontrado->competencias()->attach($competencias->last(), ['origem' => 'curriculo']);
        $outro = Candidato::factory()->create(['codigo' => 'CAND-7001']);
        $outro->competencias()->attach($competencias->modelKeys(), ['origem' => 'curriculo']);
        $semCompetencias = Candidato::factory()->create(['codigo' => 'CAND-6002']);

        $resposta = $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', [
            $vaga, 'competencias' => $competencias->modelKeys(), 'busca' => 'CAND-60',
        ]));

        $resposta->assertOk()->assertSee($encontrado->codigo)
            ->assertDontSee($outro->codigo)->assertDontSee($semCompetencias->codigo);
    }

    #[TestWith(['nome'])]
    #[TestWith(['email'])]
    public function test_busca_na_triagem_nao_encontra_candidato_por_identidade_em_html_ou_ajax(string $campo): void
    {
        $vaga = Vaga::factory()->create();
        $competencia = Competencia::factory()->hard()->create();
        $candidato = Candidato::factory()->create(['nome' => 'Ana Identificavel', 'email' => 'ana.identificavel@exemplo.com']);
        $candidato->competencias()->attach($competencia, ['origem' => 'curriculo']);
        $url = route('vagas.triagem.index', [
            $vaga, 'busca' => $candidato->{$campo}, 'competencias' => [$competencia->id], 'somente_por_codigo' => '0',
        ]);
        $this->actingAs(User::factory()->create());

        $this->get($url)->assertOk()->assertSee('0 candidatos disponíveis')->assertDontSee($candidato->codigo);
        $resposta = $this->getJson($url);

        $resposta->assertOk();
        $html = $resposta->json('fragments.resultados-candidatos');
        $this->assertStringContainsString('0 candidatos disponíveis', $html);
        $this->assertStringNotContainsString($candidato->codigo, $html);
    }

    #[TestWith(['nome'])]
    #[TestWith(['email'])]
    public function test_selecionar_todos_nao_permite_filtrar_por_identidade_no_backend(string $campo): void
    {
        $vaga = Vaga::factory()->create();
        $candidato = Candidato::factory()->create(['nome' => 'Ana Identificavel', 'email' => 'ana.identificavel@exemplo.com']);

        $resposta = $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), [
            'todos_filtrados' => '1', 'busca' => $candidato->{$campo}, 'somente_por_codigo' => '0',
        ]);

        $resposta->assertSessionHasErrors(['candidatos' => 'Selecione ao menos um candidato para adicionar à triagem.']);
        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_adicao_rejeita_inativos_sem_adicionar_parte_da_selecao(): void
    {
        $vaga = Vaga::factory()->create();
        $ativo = Candidato::factory()->create();
        $inativo = Candidato::factory()->desativado()->create();

        $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), ['candidatos' => [$ativo->id, $inativo->id]])
            ->assertSessionHasErrors(['candidatos.1' => 'Selecione apenas candidatos ativos do banco de talentos.']);

        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_adicao_exige_ao_menos_um_candidato(): void
    {
        $vaga = Vaga::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('vagas.triagem.store', $vaga), [])
            ->assertSessionHasErrors(['candidatos' => 'Selecione ao menos um candidato para adicionar à triagem.']);

        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_vaga_encerrada_recusa_adicao_e_nao_oferece_selecao(): void
    {
        $vaga = Vaga::factory()->encerrada()->create();
        $candidato = Candidato::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->post(route('vagas.triagem.store', $vaga), ['candidatos' => [$candidato->id]])
            ->assertSessionHasErrors('vaga');
        $this->get(route('vagas.triagem.index', $vaga))->assertRedirect(route('vagas.show', $vaga));
        $this->getJson(route('vagas.triagem.index', $vaga))->assertUnprocessable()
            ->assertJsonValidationErrors(['vaga' => 'Vaga encerrada — somente consulta. Não é possível adicionar candidatos.']);
        $this->getJson(route('vagas.show', $vaga))->assertOk()->assertJsonPath('data.quadro-vaga.vaga.selecionarUrl', null);

        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_rotas_de_selecao_exigem_autenticacao(): void
    {
        $vaga = Vaga::factory()->create();

        $this->get(route('vagas.triagem.index', $vaga))->assertRedirect(route('login'));
        $this->getJson(route('vagas.triagem.index', $vaga))->assertUnauthorized();
        $this->post(route('vagas.triagem.store', $vaga), [])->assertRedirect(route('login'));
    }

    public function test_candidato_sem_vinculo_nao_pode_abrir_perfil_nem_ser_movido_na_vaga(): void
    {
        $vaga = Vaga::factory()->create();
        $candidato = Candidato::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get(route('vagas.candidatos.show', [$vaga, $candidato->codigo]))->assertNotFound();
        $this->patchJson(route('vagas.candidatos.etapa', [$vaga, $candidato->codigo]), ['etapa' => 'entrevista'])
            ->assertUnprocessable()->assertJsonValidationErrors('etapa');

        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_importacao_exige_vaga_aberta_e_nao_tipo_de_candidato(): void
    {
        Storage::fake('curriculos');
        $vaga = Vaga::factory()->encerrada()->create();
        $this->actingAs(User::factory()->create());

        $this->post(route('importacoes.store'), ['arquivos' => [$this->pdf()]])->assertSessionHasErrors('vaga_id');
        $this->post(route('importacoes.store'), ['arquivos' => [$this->pdf()], 'vaga_id' => $vaga->id])
            ->assertSessionHasErrors(['vaga_id' => 'Selecione uma vaga aberta.']);
        $this->get(route('importacoes.create'))->assertOk()->assertSee('Processo seletivo')->assertDontSee('name="tipo"', false);

        $this->assertDatabaseCount('candidatos', 0);
        $this->assertSame([], Storage::disk('curriculos')->allFiles());
    }

    public function test_importacao_confirmada_cria_candidato_sem_tipo_e_o_vincula_a_vaga_da_sessao(): void
    {
        Storage::fake('curriculos');
        $vaga = Vaga::factory()->create(['tipo_candidato' => null]);
        $outra = Vaga::factory()->create();
        $this->actingAs(User::factory()->create());
        $uuid = $this->importar($vaga);
        $this->assertDatabaseCount('candidatos', 0);
        $this->get(route('importacoes.show', $uuid))->assertOk()->assertSee($vaga->titulo);

        $this->post(route('importacoes.confirmar', $uuid), [...$this->revisao(), 'vaga_id' => $outra->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $candidato = Candidato::query()->sole();
        $this->assertNull($candidato->tipo);
        $this->assertDatabaseHas('participacoes', ['vaga_id' => $vaga->id, 'candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $this->assertDatabaseMissing('participacoes', ['vaga_id' => $outra->id]);
        Storage::disk('curriculos')->assertExists($candidato->curriculo_arquivo);
        Storage::disk('curriculos')->assertMissing("tmp/{$uuid}.pdf");
    }

    public function test_reimportar_atualiza_cadastro_e_preserva_outros_processos_e_etapas(): void
    {
        Storage::fake('curriculos');
        $vagas = Vaga::factory()->count(2)->create();
        $candidato = Candidato::factory()->create(['email' => 'ana@exemplo.com.br']);
        Participacao::query()->create(['vaga_id' => $vagas[0]->id, 'candidato_id' => $candidato->id, 'etapa' => 'aprovado']);
        $this->actingAs(User::factory()->create());
        $uuid = $this->importar($vagas[1]);

        $this->post(route('importacoes.confirmar', $uuid), [...$this->revisao(), 'modo' => 'atualizar', 'candidato_existente_id' => $candidato->id])
            ->assertRedirect(route('candidatos.show', $candidato))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('candidatos', 1);
        $this->assertDatabaseHas('participacoes', ['vaga_id' => $vagas[0]->id, 'candidato_id' => $candidato->id, 'etapa' => 'aprovado']);
        $this->assertDatabaseHas('participacoes', ['vaga_id' => $vagas[1]->id, 'candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        Storage::disk('curriculos')->assertExists($candidato->fresh()->curriculo_arquivo);
    }

    public function test_encerrar_vaga_entre_upload_e_confirmacao_nao_salva_candidato_nem_perde_pdf_temporario(): void
    {
        Storage::fake('curriculos');
        $vaga = Vaga::factory()->create();
        $this->actingAs(User::factory()->create());
        $uuid = $this->importar($vaga);
        $vaga->update(['status' => 'encerrada', 'encerrada_em' => now()]);

        $this->post(route('importacoes.confirmar', $uuid), $this->revisao())->assertSessionHasErrors('vaga');

        $this->assertDatabaseCount('candidatos', 0);
        $this->assertDatabaseCount('participacoes', 0);
        Storage::disk('curriculos')->assertExists("tmp/{$uuid}.pdf");
        $this->assertCount(1, Storage::disk('curriculos')->allFiles());
    }

    public function test_lote_sem_revisao_vincula_todos_os_candidatos_a_vaga_selecionada(): void
    {
        Storage::fake('curriculos');
        $this->seed(CompetenciaSeeder::class);
        $vaga = Vaga::factory()->create();
        $this->actingAs(User::factory()->create());
        $resposta = $this->post(route('importacoes.store'), [
            'vaga_id' => $vaga->id,
            'arquivos' => [$this->pdf(), $this->pdf('Bruno Lima', 'bruno@exemplo.com.br')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $lote = Str::afterLast($resposta->headers->get('Location'), '/');

        $this->post(route('importacoes.lotes.cadastrar', $lote), ['consentimento_meio' => 'email', 'consentimento_em' => now()->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('candidatos', 2);
        $this->assertSame(2, Participacao::query()->where('vaga_id', $vaga->id)->where('etapa', 'triagem')->count());
        foreach (Candidato::all() as $candidato) {
            Storage::disk('curriculos')->assertExists($candidato->curriculo_arquivo);
        }
    }

    public function test_revisao_individual_do_lote_mantem_o_processo_de_todos_os_curriculos(): void
    {
        Storage::fake('curriculos');
        $vaga = Vaga::factory()->create();
        $this->actingAs(User::factory()->create());
        $resposta = $this->post(route('importacoes.store'), [
            'vaga_id' => $vaga->id, 'arquivos' => [$this->pdf(), $this->pdf('Bruno Lima', 'bruno@exemplo.com.br')],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $lote = Str::afterLast($resposta->headers->get('Location'), '/');
        $itens = session("importacoes_lotes.{$lote}");

        foreach ($itens as $indice => $item) {
            $this->post(route('importacoes.confirmar', $item['uuid']), [
                ...$this->revisao(), 'email' => $indice === 0 ? 'ana@exemplo.com.br' : 'bruno@exemplo.com.br',
            ])->assertRedirect()->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('candidatos', 2);
        $this->assertSame(2, Participacao::query()->where('vaga_id', $vaga->id)->where('etapa', 'triagem')->count());
        foreach (Candidato::all() as $candidato) {
            Storage::disk('curriculos')->assertExists($candidato->curriculo_arquivo);
        }
    }

    public function test_banco_filtra_por_processo_e_painel_conta_talentos_sem_divisao_de_tipo(): void
    {
        $vaga = Vaga::factory()->create();
        $vinculado = Candidato::factory()->create(['tipo' => null]);
        $outro = Candidato::factory()->create(['tipo' => null]);
        Participacao::query()->create(['vaga_id' => $vaga->id, 'candidato_id' => $vinculado->id, 'etapa' => 'triagem']);
        $this->actingAs(User::factory()->create());

        $this->get(route('candidatos.index', ['vaga_id' => $vaga->id]))->assertOk()
            ->assertSee($vinculado->nome)->assertDontSee($outro->nome)->assertSee($vaga->titulo)->assertDontSee('Tipo de candidato');
        $this->get(route('painel'))->assertOk()->assertViewHas('totalTalentos', 2)
            ->assertDontSee('Servidores')->assertDontSee('Estagiários');
    }

    private function pdf(string $nome = 'Ana Lima', string $email = 'ana@exemplo.com.br'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('curriculo.pdf', (new GeradorPdfCurriculo)->gerar([
            $nome, $email, 'FORMAÇÃO', 'Análise e Desenvolvimento de Sistemas', 'COMPETÊNCIAS', 'Python, SQL',
        ]));
    }

    private function importar(Vaga $vaga): string
    {
        $resposta = $this->post(route('importacoes.store'), ['arquivos' => [$this->pdf()], 'vaga_id' => $vaga->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        return Str::afterLast($resposta->headers->get('Location'), '/');
    }

    /** @return array<string, mixed> */
    private function revisao(): array
    {
        return [
            'nome' => 'Ana Lima', 'email' => 'ana@exemplo.com.br', 'area' => 'desenvolvimento',
            'formacoes' => [], 'experiencias' => [], 'projetos' => [], 'links' => [],
            'consentimento_meio' => 'email', 'consentimento_em' => now()->toDateString(), 'modo' => 'novo',
        ];
    }
}
