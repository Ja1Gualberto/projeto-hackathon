<?php

namespace Tests\Feature;

use App\Enums\OrigemCompetencia;
use App\Enums\StatusCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\RegistroExclusao;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LgpdTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_mostra_consentimento_responsavel_retencao_e_acoes_sem_codigo(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        $usuario = User::factory()->create(['name' => 'Responsável pelo consentimento']);
        $candidato = Candidato::factory()->create([
            'consentimento_meio' => 'email', 'consentimento_em' => '2024-01-10',
            'consentimento_registrado_por' => $usuario->id, 'dados_atualizados_em' => '2024-01-15 12:00:00',
        ]);

        $this->actingAs($usuario)->get(route('candidatos.show', $candidato))
            ->assertOk()->assertSee('E-mail')->assertSee('10/01/2024')
            ->assertSee('Responsável pelo consentimento')->assertSee('Retenção até 15/01/2026')
            ->assertSee('revisão de retenção pendente')->assertSee('Excluir definitivamente')
            ->assertSee(route('candidatos.edit', $candidato))->assertDontSee($candidato->codigo)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_edicao_preenche_campos_pessoais_ocultos_na_serializacao(): void
    {
        $candidato = Candidato::factory()->create(['nome' => 'Ana Corrigível', 'email' => 'ana@exemplo.com.br', 'cpf' => '12345678901']);

        $this->actingAs(User::factory()->create())->get(route('candidatos.edit', $candidato))
            ->assertOk()->assertSee('Ana Corrigível')->assertSee('ana@exemplo.com.br')->assertSee('12345678901')
            ->assertSee('data-ajax', false)->assertDontSee($candidato->codigo)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_correcao_renova_retencao_preserva_origens_e_ignora_campos_protegidos(bool $json): void
    {
        $this->freezeTime();
        $candidato = Candidato::factory()->retencaoVencida()->create();
        $observada = Competencia::factory()->soft()->create();
        $nova = Competencia::factory()->hard()->create();
        $removida = Competencia::factory()->hard()->create();
        $candidato->competencias()->attach($observada, ['origem' => 'entrevista']);
        $candidato->competencias()->attach($removida, ['origem' => 'curriculo']);
        $codigo = $candidato->codigo;
        $dados = [...$this->dados(), 'competencias' => [$observada->id, $nova->id],
            'status' => 'desativado', 'codigo' => 'CAND-0000', 'curriculo_arquivo' => 'outro.pdf'];
        $this->actingAs(User::factory()->create());

        $resposta = $json ? $this->putJson(route('candidatos.update', $candidato), $dados)
            : $this->put(route('candidatos.update', $candidato), $dados);

        if ($json) {
            $resposta->assertOk()->assertJsonPath('redirect', route('candidatos.show', $candidato))
                ->assertJsonPath('message', 'Dados do candidato atualizados.')
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame([], (array) $resposta->json('fragments'));
        } else {
            $resposta->assertRedirect(route('candidatos.show', $candidato))->assertSessionHasNoErrors();
        }
        $candidato->refresh();
        $this->assertSame('Nome corrigido', $candidato->nome);
        $this->assertSame('12345678901', $candidato->cpf);
        $this->assertSame(['Formação revisada', 'Outra formação'], $candidato->formacoes);
        $this->assertSame(now()->toDateTimeString(), $candidato->dados_atualizados_em->toDateTimeString());
        $this->assertSame(StatusCandidato::Ativo, $candidato->status);
        $this->assertSame($codigo, $candidato->codigo);
        $this->assertNull($candidato->curriculo_arquivo);
        $this->assertDatabaseHas('candidato_competencia', ['candidato_id' => $candidato->id, 'competencia_id' => $observada->id, 'origem' => 'entrevista']);
        $this->assertDatabaseHas('candidato_competencia', ['candidato_id' => $candidato->id, 'competencia_id' => $nova->id, 'origem' => 'revisao']);
        $this->assertDatabaseMissing('candidato_competencia', ['candidato_id' => $candidato->id, 'competencia_id' => $removida->id]);
    }

    public function test_correcao_invalida_retorna_422_sem_alterar_cadastro(): void
    {
        $candidato = Candidato::factory()->create(['nome' => 'Nome original']);

        $this->actingAs(User::factory()->create())->putJson(route('candidatos.update', $candidato), [
            ...$this->dados(), 'nome' => '', 'email' => 'inválido', 'consentimento_meio' => '',
            'consentimento_em' => '', 'links' => 'javascript:alert(1)', 'competencias' => [999999],
        ])->assertUnprocessable()->assertJsonValidationErrors(['nome', 'email', 'consentimento_meio', 'consentimento_em', 'links.0', 'competencias.0'])
            ->assertJsonPath('errors.consentimento_meio.0', 'Registre como e quando o candidato consentiu com o uso dos dados.');

        $this->assertSame('Nome original', $candidato->fresh()->nome);
    }

    public function test_erro_html_preserva_preenchimento_da_correcao(): void
    {
        $candidato = Candidato::factory()->create();

        $this->actingAs(User::factory()->create())->from(route('candidatos.edit', $candidato))
            ->put(route('candidatos.update', $candidato), [...$this->dados(), 'email' => 'email inválido'])
            ->assertRedirect(route('candidatos.edit', $candidato))->assertSessionHasErrors('email')
            ->assertSessionHasInput('nome', 'Nome corrigido');
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_status_reversivel_retira_da_busca_e_triagem_sem_apagar_dados(bool $json): void
    {
        $candidato = Candidato::factory()->estagiario()->create();
        $vaga = Vaga::factory()->paraEstagiarios()->create();
        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $competencia = Competencia::factory()->create();
        $vaga->competencias()->attach($competencia, ['peso' => 3]);
        $atualizacao = $candidato->dados_atualizados_em->toDateTimeString();
        $this->actingAs(User::factory()->create());

        $rota = route('candidatos.status', $candidato);
        $resposta = $json ? $this->patchJson($rota, ['status' => 'desativado']) : $this->patch($rota, ['status' => 'desativado']);

        if ($json) {
            $resposta->assertOk()->assertJsonPath('redirect', null)->assertJsonStructure(['fragments' => ['situacao-candidato', 'secao-lgpd']])
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertStringContainsString('Desativado', $resposta->json('fragments.situacao-candidato'));
            $this->assertStringContainsString('Reativar', $resposta->json('fragments.secao-lgpd'));
            $this->assertStringNotContainsString($candidato->codigo, json_encode($resposta->json(), JSON_UNESCAPED_UNICODE));
        } else {
            $resposta->assertRedirect(route('candidatos.show', $candidato))->assertSessionHasNoErrors();
        }
        $this->assertSame(StatusCandidato::Desativado, $candidato->fresh()->status);
        $this->assertSame($atualizacao, $candidato->fresh()->dados_atualizados_em->toDateTimeString());
        $this->get(route('candidatos.index'))->assertDontSee($candidato->nome);
        $this->get(route('vagas.show', $vaga))->assertOk()->assertDontSee($candidato->codigo);
        $this->get(route('candidatos.index', ['situacao' => 'desativados']))->assertSee($candidato->nome);

        $this->patchJson($rota, ['status' => 'ativo'])->assertOk();
        $this->get(route('candidatos.index'))->assertSee($candidato->nome);
        $this->get(route('vagas.show', $vaga))->assertSee($candidato->codigo);
    }

    public function test_status_invalido_retorna_422_sem_alterar_cadastro(): void
    {
        $candidato = Candidato::factory()->create();
        $this->actingAs(User::factory()->create())->patchJson(route('candidatos.status', $candidato), ['status' => 'excluido'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame(StatusCandidato::Ativo, $candidato->fresh()->status);
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_filtros_de_retencao_combinam_situacao_competencias_e_tipo(bool $json): void
    {
        $this->freezeTime();
        $vencido = Candidato::factory()->estagiario()->retencaoVencida()->create();
        $atual = Candidato::factory()->estagiario()->create();
        $desativado = Candidato::factory()->estagiario()->retencaoVencida()->desativado()->create();
        $servidor = Candidato::factory()->servidor()->retencaoVencida()->create();
        $competencia = Competencia::factory()->create();
        $vencido->competencias()->attach($competencia, ['origem' => 'curriculo']);
        $rota = route('candidatos.index', ['situacao' => 'retencao', 'tipo' => 'estagiario', 'competencias' => [$competencia->id]]);
        $this->actingAs(User::factory()->create());

        $resposta = $json ? $this->getJson($rota) : $this->get($rota);

        $resposta->assertOk();
        $html = $json ? $resposta->json('fragments.resultados-candidatos') : $resposta->getContent();
        $this->assertStringContainsString($vencido->nome, $html);
        $this->assertStringContainsString('revisão de retenção pendente', $html);
        foreach ([$atual, $desativado, $servidor] as $excluido) {
            $this->assertStringNotContainsString($excluido->nome, $html);
        }
        $this->get(route('painel'))->assertOk()->assertViewHas('retencaoPendente', 2)
            ->assertSee('Retenção pendente')->assertSee(route('candidatos.index', ['situacao' => 'retencao']));
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_exclusao_remove_dados_vinculos_e_pdf_e_mantem_registro_anonimo(bool $json): void
    {
        Storage::fake('curriculos');
        $usuario = User::factory()->create();
        $candidato = Candidato::factory()->estagiario()->create(['curriculo_arquivo' => 'excluir.pdf']);
        Storage::disk('curriculos')->put('excluir.pdf', 'PDF fictício');
        $competencia = Competencia::factory()->create();
        $candidato->competencias()->attach($competencia, ['origem' => OrigemCompetencia::Entrevista]);
        $vaga = Vaga::factory()->paraEstagiarios()->create();
        $vaga->competencias()->attach($competencia, ['peso' => 3]);
        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $this->actingAs($usuario)->patch(route('vagas.candidatos.etapa', [$vaga, $candidato->codigo]), ['etapa' => 'entrevista'])->assertSessionHasNoErrors();
        $this->post(route('candidatos.anotacoes.store', $candidato), ['texto' => 'Anotação privada', 'competencias_observadas' => [$competencia->id]])->assertSessionHasNoErrors();
        $candidato->perfilEnergia()->create(['dinamico_analitico' => 3, 'individual_equipe' => 3, 'execucao_planejamento' => 3, 'justificativa' => 'Privado', 'origem' => 'humano', 'analisado_em' => now()]);

        $rota = route('candidatos.destroy', $candidato);
        $dados = ['motivo' => 'pedido_titular', 'confirmacao' => '1'];
        $resposta = $json ? $this->deleteJson($rota, $dados) : $this->delete($rota, $dados);

        if ($json) {
            $resposta->assertOk()->assertJsonPath('redirect', route('candidatos.index'))
                ->assertHeader('Cache-Control', 'no-store, private');
        } else {
            $resposta->assertRedirect(route('candidatos.index'));
        }
        $this->assertModelMissing($candidato);
        foreach (['candidato_competencia', 'participacoes', 'movimentacoes_etapa', 'anotacoes', 'anotacao_competencia', 'perfis_energia'] as $tabela) {
            $this->assertDatabaseCount($tabela, 0);
        }
        Storage::disk('curriculos')->assertMissing('excluir.pdf');
        $registro = RegistroExclusao::query()->sole();
        $this->assertSame(['id', 'user_id', 'motivo', 'excluido_em'], array_keys($registro->getAttributes()));
        $this->assertSame($usuario->id, $registro->user_id);
        $this->assertSame('pedido_titular', $registro->motivo->value);
        $this->assertNotNull($registro->excluido_em);
        $this->assertModelExists($competencia);
        $this->get(route('vagas.show', $vaga))->assertOk()->assertDontSee($candidato->codigo);
        $this->get(route('candidatos.curriculo', $candidato))->assertNotFound();
        $this->get(route('candidatos.index'))->assertDontSee($candidato->nome);
        $this->deleteJson($rota, $dados)->assertNotFound();
        $this->assertDatabaseCount('registros_exclusao', 1);
    }

    #[TestWith([[], ['motivo', 'confirmacao']])]
    #[TestWith([['motivo' => 'outro'], ['confirmacao']])]
    #[TestWith([['motivo' => 'invalido', 'confirmacao' => '1'], ['motivo']])]
    #[TestWith([['motivo' => 'outro', 'confirmacao' => '0'], ['confirmacao']])]
    public function test_exclusao_invalida_retorna_422_sem_remover_dados(array $dados, array $erros): void
    {
        Storage::fake('curriculos');
        $candidato = Candidato::factory()->create(['curriculo_arquivo' => 'mantido.pdf']);
        Storage::disk('curriculos')->put('mantido.pdf', 'PDF fictício');

        $this->actingAs(User::factory()->create())->deleteJson(route('candidatos.destroy', $candidato), $dados)
            ->assertUnprocessable()->assertJsonValidationErrors($erros);

        $this->assertModelExists($candidato);
        $this->assertDatabaseCount('registros_exclusao', 0);
        Storage::disk('curriculos')->assertExists('mantido.pdf');
    }

    public function test_rollback_da_exclusao_preserva_pdf_e_cadastro(): void
    {
        Storage::fake('curriculos');
        $candidato = Candidato::factory()->create(['curriculo_arquivo' => 'rollback.pdf']);
        Storage::disk('curriculos')->put('rollback.pdf', 'PDF fictício');
        $this->actingAs(User::factory()->create());
        DB::beginTransaction();

        $this->deleteJson(route('candidatos.destroy', $candidato), ['motivo' => 'retencao', 'confirmacao' => '1'])->assertOk();
        Storage::disk('curriculos')->assertExists('rollback.pdf');
        DB::rollBack();

        $this->assertModelExists($candidato);
        $this->assertDatabaseCount('registros_exclusao', 0);
        Storage::disk('curriculos')->assertExists('rollback.pdf');
    }

    public function test_visitante_nao_pode_corrigir_desativar_ou_excluir(): void
    {
        $candidato = Candidato::factory()->create();

        $this->get(route('candidatos.edit', $candidato))->assertRedirect(route('login'));
        $this->putJson(route('candidatos.update', $candidato), $this->dados())->assertUnauthorized();
        $this->patchJson(route('candidatos.status', $candidato), ['status' => 'desativado'])->assertUnauthorized();
        $this->deleteJson(route('candidatos.destroy', $candidato), ['motivo' => 'outro', 'confirmacao' => '1'])->assertUnauthorized();
        $this->assertModelExists($candidato);
        $this->assertDatabaseCount('registros_exclusao', 0);
    }

    public function test_retencao_respeita_prazo_configurado_e_nao_inclui_data_limite_ou_desativados(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        config(['banco_talentos.retencao_anos' => 1]);
        $vencido = Candidato::factory()->create(['dados_atualizados_em' => '2025-10-31 23:59:59']);
        $limite = Candidato::factory()->create(['dados_atualizados_em' => '2025-11-01 00:00:00']);
        $desativado = Candidato::factory()->retencaoVencida()->desativado()->create();

        $resposta = $this->actingAs(User::factory()->create())->getJson(route('candidatos.index', ['situacao' => 'retencao']));

        $resposta->assertOk();
        $html = $resposta->json('fragments.resultados-candidatos');
        $this->assertStringContainsString($vencido->nome, $html);
        $this->assertStringNotContainsString($limite->nome, $html);
        $this->assertStringNotContainsString($desativado->nome, $html);
        $this->get(route('painel'))->assertViewHas('retencaoPendente', 1);
    }

    public function test_busca_json_de_desativados_preserva_paginacao_e_nao_lista_ativos(): void
    {
        Candidato::factory()->desativado()->count(13)->create();
        $ativo = Candidato::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->getJson(route('candidatos.index', ['situacao' => 'desativados']));

        $resposta->assertOk();
        $html = $resposta->json('fragments.resultados-candidatos');
        $this->assertStringContainsString('13 talentos encontrados', $html);
        $this->assertStringContainsString('Perfis desativados', $html);
        $this->assertStringContainsString('situacao=desativados', $html);
        $this->assertStringContainsString('page=2', $html);
        $this->assertStringNotContainsString($ativo->nome, $html);
        $this->getJson(route('candidatos.index', ['situacao' => 'desativados', 'page' => 2]))->assertOk();
    }

    public function test_perfil_e_fragmentos_lgpd_escapam_nome_do_responsavel(): void
    {
        $usuario = User::factory()->create(['name' => '<script>responsavel()</script>']);
        $candidato = Candidato::factory()->create(['consentimento_registrado_por' => $usuario->id]);
        $this->actingAs($usuario);

        $this->get(route('candidatos.show', $candidato))->assertOk()->assertSee($usuario->name)->assertDontSee($usuario->name, false);
        $resposta = $this->patchJson(route('candidatos.status', $candidato), ['status' => 'desativado']);

        $resposta->assertOk();
        $this->assertStringContainsString(e($usuario->name), $resposta->json('fragments.secao-lgpd'));
        $this->assertStringNotContainsString($usuario->name, $resposta->json('fragments.secao-lgpd'));
    }

    /** @return array<string, mixed> */
    private function dados(): array
    {
        return ['nome' => 'Nome corrigido', 'email' => 'corrigido@exemplo.com.br', 'cpf' => '123.456.789-01',
            'tipo' => 'estagiario', 'area' => 'dados', 'formacoes' => "Formação revisada\n\n Outra formação",
            'experiencias' => '', 'projetos' => '', 'links' => 'https://example.com/portfolio',
            'consentimento_meio' => 'email', 'consentimento_em' => now()->toDateString()];
    }
}
