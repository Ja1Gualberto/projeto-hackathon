<?php

namespace Tests\Feature;

use App\Enums\EtapaSelecao;
use App\Enums\OrigemCompetencia;
use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\MovimentacaoEtapa;
use App\Models\Participacao;
use App\Models\User;
use App\Models\Vaga;
use Database\Seeders\CandidatoSeeder;
use Database\Seeders\CompetenciaSeeder;
use Database\Seeders\UsuarioSeeder;
use Database\Seeders\VagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

/**
 * Anotações de entrevista (US5, FR-026/FR-027) pelo banco e pela vaga revelada, nos modos HTML e
 * JSON (T113, T161).
 */
class AnotacaoEntrevistaTest extends TestCase
{
    use RefreshDatabase;

    private User $recrutador;

    private Candidato $candidato;

    private Vaga $vaga;

    private Competencia $comunicacao;

    private Competencia $python;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recrutador = User::factory()->create(['name' => 'Recrutadora Teste']);
        $this->actingAs($this->recrutador);
        $this->comunicacao = Competencia::factory()->soft()->create(['nome' => 'Comunicação']);
        $this->python = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $this->candidato = Candidato::factory()->estagiario()->create(['nome' => 'Joana Prates']);
        $this->candidato->competencias()->attach($this->comunicacao, ['origem' => OrigemCompetencia::Curriculo->value]);
        $this->vaga = $this->vaga('Estagiário de Dados');
    }

    private function vaga(string $titulo, array $atributos = []): Vaga
    {
        $vaga = Vaga::factory()->paraEstagiarios()->create(['titulo' => $titulo, ...$atributos]);
        $vaga->competencias()->attach($this->python, ['peso' => 3]);

        return $vaga;
    }

    private function moverParaEntrevista(?Vaga $vaga = null): void
    {
        $this->patch(route('vagas.candidatos.etapa', [$vaga ?? $this->vaga, $this->candidato->codigo]), ['etapa' => 'entrevista'])
            ->assertSessionHasNoErrors();
    }

    private function anotarPelaVaga(array $dados, ?Vaga $vaga = null, bool $json = false): TestResponse
    {
        $rota = route('vagas.candidatos.anotacoes.store', [$vaga ?? $this->vaga, $this->candidato->codigo]);

        return $json ? $this->postJson($rota, $dados) : $this->post($rota, $dados);
    }

    private function anotarPeloBanco(array $dados, bool $json = false): TestResponse
    {
        $rota = route('candidatos.anotacoes.store', $this->candidato);

        return $json ? $this->postJson($rota, $dados) : $this->post($rota, $dados);
    }

    private function origem(Competencia $competencia): ?OrigemCompetencia
    {
        $origem = $this->candidato->competencias()->whereKey($competencia->id)->first()?->pivot->origem;

        return $origem === null ? null : OrigemCompetencia::from($origem);
    }

    public function test_anotacao_pela_vaga_grava_autor_vaga_texto_e_competencias_observadas(): void
    {
        $this->moverParaEntrevista();
        $outra = $this->vaga('Outra vaga');

        $this->anotarPelaVaga([
            'texto' => 'Explicou com clareza o projeto de dados abertos.',
            'vaga_id' => $outra->id,
            'competencias_observadas' => [$this->comunicacao->id, $this->python->id],
        ])->assertRedirect(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]))
            ->assertSessionHas('status', 'Anotação registrada.');

        $anotacao = Anotacao::query()->sole();
        $this->assertSame($this->recrutador->id, $anotacao->user_id);
        $this->assertSame($this->vaga->id, $anotacao->vaga_id);
        $this->assertSame($this->candidato->id, $anotacao->candidato_id);
        $this->assertSame('Explicou com clareza o projeto de dados abertos.', $anotacao->texto);
        $this->assertEqualsCanonicalizing([$this->comunicacao->id, $this->python->id], $anotacao->competenciasObservadas()->pluck('competencias.id')->all());
    }

    public function test_competencia_observada_passa_a_ter_origem_entrevista(): void
    {
        $git = Competencia::factory()->hard()->create(['nome' => 'Git']);
        $this->candidato->competencias()->attach($git, ['origem' => OrigemCompetencia::Revisao->value]);

        $this->anotarPeloBanco([
            'texto' => 'Boa comunicação e domínio de Python e Git.',
            'competencias_observadas' => [$this->comunicacao->id, $this->python->id, $git->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(OrigemCompetencia::Entrevista, $this->origem($this->comunicacao));
        $this->assertSame(OrigemCompetencia::Entrevista, $this->origem($this->python));
        $this->assertSame(OrigemCompetencia::Entrevista, $this->origem($git));
        $this->assertSame(3, $this->candidato->competencias()->count());
    }

    public function test_anotacao_pela_vaga_em_triagem_responde_403_mesmo_por_requisicao_manual(): void
    {
        $this->anotarPelaVaga(['texto' => 'Tentativa na triagem.'])
            ->assertForbidden()
            ->assertSee('Identidade protegida na triagem anônima');
        $this->anotarPelaVaga(['texto' => ''], json: true)->assertForbidden();
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Referer' => route('candidatos.show', $this->candidato)])
            ->postJson(route('vagas.candidatos.anotacoes.store', [$this->vaga, $this->candidato->codigo]), ['texto' => 'Manipulada', 'contexto' => 'perfil'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Você não tem permissão para acessar este recurso.']);

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_anotacao_pelo_banco_sem_vaga_fica_so_no_candidato(): void
    {
        $this->anotarPeloBanco(['texto' => 'Conversa inicial antes de definir a área.', 'vaga_id' => ''])
            ->assertRedirect(route('candidatos.show', $this->candidato))
            ->assertSessionHas('status', 'Anotação registrada.');

        $anotacao = Anotacao::query()->sole();
        $this->assertNull($anotacao->vaga_id);
        $this->assertSame($this->recrutador->id, $anotacao->user_id);

        $this->get(route('candidatos.show', $this->candidato))
            ->assertSee('Conversa inicial antes de definir a área.')
            ->assertSee('Sem vaga associada')
            ->assertSee('Por Recrutadora Teste em '.$anotacao->created_at->format('d/m/Y H:i'));
    }

    public function test_anotacao_pelo_banco_pode_escolher_a_vaga(): void
    {
        $this->anotarPeloBanco(['texto' => 'Entrevista para a vaga de dados.', 'vaga_id' => $this->vaga->id])->assertSessionHasNoErrors();

        $this->assertSame($this->vaga->id, Anotacao::query()->sole()->vaga_id);
        $this->get(route('candidatos.show', $this->candidato))->assertSee('Vaga: Estagiário de Dados');
    }

    public function test_historico_vem_do_mais_recente_para_o_mais_antigo(): void
    {
        foreach (['Primeira conversa.' => 3, 'Segunda conversa.' => 2, 'Terceira conversa.' => 1] as $texto => $diasAtras) {
            Anotacao::factory()->create([
                'candidato_id' => $this->candidato->id,
                'user_id' => $this->recrutador->id,
                'texto' => $texto,
                'created_at' => now()->subDays($diasAtras),
            ]);
        }

        $this->get(route('candidatos.show', $this->candidato))
            ->assertSeeInOrder(['Terceira conversa.', 'Segunda conversa.', 'Primeira conversa.', 'Registrar anotação']);

        $this->moverParaEntrevista();
        $this->get(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]))
            ->assertSeeInOrder(['Terceira conversa.', 'Segunda conversa.', 'Primeira conversa.', 'Registrar anotação']);
    }

    public function test_perfil_em_triagem_nao_mostra_anotacoes_nem_formulario_e_revelado_em_outra_vaga_mostra_todas(): void
    {
        $outra = $this->vaga('Vaga B');
        $this->moverParaEntrevista();
        $this->anotarPelaVaga(['texto' => 'Anotação feita na vaga A.']);

        $this->get(route('vagas.candidatos.show', [$outra, $this->candidato->codigo]))
            ->assertOk()
            ->assertDontSee('Anotação feita na vaga A.')
            ->assertDontSee('Registrar anotação');

        $this->moverParaEntrevista($outra);

        $this->get(route('vagas.candidatos.show', [$outra, $this->candidato->codigo]))
            ->assertSee('Anotação feita na vaga A.')
            ->assertSee('Vaga: Estagiário de Dados')
            ->assertSee('Registrar anotação');
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    public function test_texto_vazio_e_rejeitado(string $texto): void
    {
        $this->anotarPeloBanco(['texto' => $texto])->assertSessionHasErrors(['texto' => 'Escreva a anotação antes de registrar.']);
        $this->anotarPeloBanco(['texto' => $texto], json: true)->assertUnprocessable()->assertJsonValidationErrors('texto');

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_texto_com_mais_de_5000_caracteres_e_rejeitado(): void
    {
        $this->anotarPeloBanco(['texto' => str_repeat('a', 5001)])->assertSessionHasErrors('texto');
        $this->assertSame(0, Anotacao::query()->count());

        $this->anotarPeloBanco(['texto' => str_repeat('a', 5000)])->assertSessionHasNoErrors();
        $this->assertSame(1, Anotacao::query()->count());
    }

    public function test_vaga_e_competencia_inexistentes_sao_rejeitadas(): void
    {
        $this->anotarPeloBanco(['texto' => 'Válida.', 'vaga_id' => 999])->assertSessionHasErrors('vaga_id');
        $this->anotarPeloBanco(['texto' => 'Válida.', 'competencias_observadas' => [999]])->assertSessionHasErrors('competencias_observadas.0');

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_vaga_encerrada_nao_aceita_anotacao_pela_vaga_mas_o_banco_aceita(): void
    {
        $this->moverParaEntrevista();
        $this->vaga->update(['status' => 'encerrada', 'encerrada_em' => now()]);

        $this->get(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]))->assertDontSee('Registrar anotação');
        $this->anotarPelaVaga(['texto' => 'Depois de encerrar.'])
            ->assertSessionHasErrors(['texto' => 'Vaga encerrada — somente consulta. Registre a anotação pela gestão do banco.']);
        $this->assertSame(0, Anotacao::query()->count());

        $this->anotarPeloBanco(['texto' => 'Depois de encerrar.', 'vaga_id' => $this->vaga->id])->assertSessionHasNoErrors();
        $this->assertSame(1, Anotacao::query()->count());
    }

    public function test_perfil_do_banco_mostra_formulario_com_vagas_e_competencias(): void
    {
        $this->get(route('candidatos.show', $this->candidato))
            ->assertOk()
            ->assertSee('data-abrir-modal="nova-anotacao"', false)
            ->assertDontSee('<dialog id="anotacoes-entrevista"', false)
            ->assertDontSee('data-abrir-ao-carregar', false)
            ->assertSeeInOrder(['Adicionar nova anotação', 'Nenhuma anotação ainda.', 'Registrar anotação'])
            ->assertSee('<dialog id="nova-anotacao"', false)
            ->assertSee('Registrar anotação')
            ->assertSee('Nenhuma anotação ainda.')
            ->assertSee('Sem vaga associada')
            ->assertSee('Estagiário de Dados')
            ->assertSee('name="competencias_observadas[]"', false)
            ->assertSee('id="historico-anotacoes"', false)
            ->assertSee('id="competencias-candidato"', false);
    }

    public function test_modal_pela_vaga_revelada_mostra_botao_no_cabecalho_e_historico_antes_do_formulario(): void
    {
        $this->moverParaEntrevista();

        $this->get(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]))
            ->assertOk()
            ->assertSee('data-abrir-modal="anotacoes-entrevista"', false)
            ->assertSee('<dialog id="anotacoes-entrevista"', false)
            ->assertSeeInOrder(['Adicionar nova anotação', 'data-fechar-modal', 'Nenhuma anotação ainda.', 'Registrar anotação']);
    }

    public function test_perfil_mostra_historico_na_secao_e_botao_no_cabecalho_abre_modal_apenas_com_formulario(): void
    {
        Anotacao::factory()->create([
            'candidato_id' => $this->candidato->id,
            'user_id' => $this->recrutador->id,
            'texto' => 'Conversa inicial sobre os projetos.',
        ]);
        Anotacao::factory()->create([
            'candidato_id' => $this->candidato->id,
            'user_id' => $this->recrutador->id,
            'vaga_id' => $this->vaga->id,
            'texto' => 'Entrevista para a vaga de dados.',
        ]);

        $resposta = $this->get(route('candidatos.show', $this->candidato));

        $resposta->assertOk()
            ->assertDontSee('Abrir PDF')
            ->assertSee('Conversa inicial sobre os projetos.')
            ->assertSee('Entrevista para a vaga de dados.');
        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(1, $xpath->query('//section[@id="entrevistas"]//section[@aria-labelledby="entrevistas-titulo"]/header/button[@data-abrir-modal="nova-anotacao"][@aria-controls="nova-anotacao"][@aria-haspopup="dialog"]'));
        $this->assertCount(0, $xpath->query('//button[@data-abrir-modal="anotacoes-entrevista"]'));
        $this->assertCount(0, $xpath->query('//dialog[@id="anotacoes-entrevista"]'));
        $this->assertCount(2, $xpath->query('//section[@id="entrevistas"]//div[@id="historico-anotacoes"]//article'));
        $this->assertCount(1, $xpath->query('//section[@id="entrevistas"]//dialog[@id="nova-anotacao"][not(@open)]//form[@data-ajax][@data-limpar-apos-sucesso]'));
        $this->assertCount(0, $xpath->query('//dialog[@id="nova-anotacao"]//div[@id="historico-anotacoes"]'));
    }

    public function test_perfil_do_banco_mostra_apenas_as_tres_anotacoes_mais_recentes_sem_apagar_as_anteriores(): void
    {
        foreach (['Primeira conversa.' => 4, 'Segunda conversa.' => 3, 'Terceira conversa.' => 2, 'Quarta conversa.' => 1] as $texto => $diasAtras) {
            Anotacao::factory()->create([
                'candidato_id' => $this->candidato->id,
                'user_id' => $this->recrutador->id,
                'texto' => $texto,
                'created_at' => now()->subDays($diasAtras),
            ]);
        }

        $resposta = $this->get(route('candidatos.show', $this->candidato));

        $resposta->assertOk()
            ->assertSeeInOrder(['Quarta conversa.', 'Terceira conversa.', 'Segunda conversa.'])
            ->assertDontSee('Primeira conversa.');
        $this->assertSame(4, $this->candidato->anotacoes()->count());
    }

    public function test_anotacao_por_ajax_atualiza_a_lista_com_as_tres_mais_recentes(): void
    {
        $this->freezeTime();
        foreach (['Primeira conversa.', 'Segunda conversa.', 'Terceira conversa.'] as $texto) {
            Anotacao::factory()->create([
                'candidato_id' => $this->candidato->id,
                'user_id' => $this->recrutador->id,
                'texto' => $texto,
            ]);
        }

        $resposta = $this->anotarPeloBanco(['texto' => 'Quarta conversa.'], json: true);

        $resposta->assertOk()->assertJsonPath('message', 'Anotação registrada.');
        $historico = $resposta->json('fragments.historico-anotacoes');
        $this->assertStringNotContainsString('Primeira conversa.', $historico);
        $documento = new \DOMDocument;
        $documento->loadHTML($historico, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(3, $xpath->query('//article'));
        $this->assertSame('Quarta conversa.', trim($xpath->query('//article/p[2]')->item(0)->textContent));
        $this->assertSame('Terceira conversa.', trim($xpath->query('//article/p[2]')->item(1)->textContent));
        $this->assertSame('Segunda conversa.', trim($xpath->query('//article/p[2]')->item(2)->textContent));
        $this->assertSame(4, $this->candidato->anotacoes()->count());
    }

    #[TestWith(['texto'])]
    #[TestWith(['vaga_id'])]
    #[TestWith(['competencias_observadas.0'])]
    public function test_erro_de_validacao_reabre_modal_do_formulario_de_nova_anotacao(string $campo): void
    {
        $erros = (new ViewErrorBag)->put('default', new MessageBag([$campo => 'Revise a anotação.']));

        $this->view('candidatos.partials._secao-anotacoes', [
            'candidato' => $this->candidato,
            'errors' => $erros,
            'anotacoes' => collect(),
            'catalogo' => ['soft' => collect([$this->comunicacao]), 'hard' => collect([$this->python])],
            'vagas' => collect([$this->vaga]),
        ])
            ->assertSee('data-abrir-ao-carregar', false)
            ->assertSee('<dialog id="nova-anotacao"', false)
            ->assertDontSee('data-regiao-recolhivel', false);
    }

    #[TestWith(['texto'])]
    #[TestWith(['vaga_id'])]
    #[TestWith(['competencias_observadas.0'])]
    public function test_erro_de_validacao_marca_modal_e_formulario_para_reabertura(string $campo): void
    {
        $erros = (new ViewErrorBag)->put('default', new MessageBag([$campo => 'Revise a anotação.']));

        $this->view('anotacoes._modal', [
            'errors' => $erros,
            'anotacoes' => collect(),
            'acao' => route('candidatos.anotacoes.store', $this->candidato),
            'descricao' => 'Anotações de Joana Prates.',
            'catalogo' => ['soft' => collect([$this->comunicacao]), 'hard' => collect([$this->python])],
            'vagas' => collect([$this->vaga]),
        ])
            ->assertSee('data-abrir-ao-carregar', false)
            ->assertSee('aria-expanded="true"', false)
            ->assertDontSee('data-regiao-recolhivel hidden', false);
    }

    public function test_visitante_nao_registra_anotacao(): void
    {
        $this->moverParaEntrevista();
        $this->app['auth']->forgetGuards();

        $this->anotarPeloBanco(['texto' => 'Sem login.'])->assertRedirect(route('login'));
        $this->anotarPelaVaga(['texto' => 'Sem login.'])->assertRedirect(route('login'));
        $this->anotarPeloBanco(['texto' => 'Sem login.'], json: true)->assertUnauthorized();

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_json_pelo_banco_devolve_historico_e_competencias_atualizados(): void
    {
        $resposta = $this->anotarPeloBanco([
            'texto' => 'Mostrou domínio de Python na conversa.',
            'vaga_id' => $this->vaga->id,
            'competencias_observadas' => [$this->python->id],
        ], json: true)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('message', 'Anotação registrada.')
            ->assertJsonPath('redirect', null);

        $this->assertSame(['historico-anotacoes', 'competencias-candidato'], array_keys($resposta->json('fragments')));
        $historico = $resposta->json('fragments.historico-anotacoes');
        $this->assertStringContainsString('Mostrou domínio de Python na conversa.', $historico);
        $this->assertStringContainsString('Por Recrutadora Teste em', $historico);
        $this->assertStringContainsString('Vaga: Estagiário de Dados', $historico);
        $this->assertStringNotContainsString('<form', $historico);
        $this->assertMatchesRegularExpression('/Python.*observada/s', $resposta->json('fragments.competencias-candidato'));

        $anotacao = Anotacao::query()->sole();
        $this->assertSame([$this->recrutador->id, $this->vaga->id], [$anotacao->user_id, $anotacao->vaga_id]);
        $this->assertSame(OrigemCompetencia::Entrevista, $this->origem($this->python));
    }

    public function test_json_pela_vaga_revelada_devolve_regioes_autorizadas_e_texto_escapado(): void
    {
        $this->moverParaEntrevista();

        $resposta = $this->anotarPelaVaga([
            'texto' => '<script>alert("x")</script> Comunicou bem.',
            'competencias_observadas' => [$this->comunicacao->id],
        ], json: true)->assertOk()->assertJsonPath('message', 'Anotação registrada.');

        $this->assertSame(['historico-anotacoes', 'competencias-candidato'], array_keys($resposta->json('fragments')));
        $historico = $resposta->json('fragments.historico-anotacoes');
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Comunicou bem.', $historico);
        $this->assertStringNotContainsString('<script>', $historico);
        $competencias = $resposta->json('fragments.competencias-candidato');
        $this->assertStringContainsString('Aderência a esta vaga', $competencias);
        $this->assertMatchesRegularExpression('/Comunicação.*observada/s', $competencias);
        $this->assertSame($this->vaga->id, Anotacao::query()->sole()->vaga_id);
    }

    public function test_json_com_texto_invalido_retorna_422_sem_gravar(): void
    {
        $this->moverParaEntrevista();

        $this->anotarPelaVaga(['texto' => str_repeat('a', 5001)], json: true)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('texto')
            ->assertJsonMissingPath('fragments');

        $this->assertSame(0, Anotacao::query()->count());
    }

    #[TestWith(['aprovado'])]
    #[TestWith(['reprovado'])]
    public function test_anotacao_no_quadro_salva_a_decisao_e_o_historico_na_mesma_vaga(string $etapa): void
    {
        $this->moverParaEntrevista();
        $outra = $this->vaga('Outra vaga');

        $this->anotarPelaVaga([
            'contexto' => 'quadro', 'texto' => 'Boa comunicação na entrevista.', 'etapa' => $etapa,
            'vaga_id' => $outra->id, 'user_id' => 999,
        ], json: true)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath("data.quadro-vaga.colunas.{$etapa}.0.codigo", $this->candidato->codigo)
            ->assertJsonCount(0, 'data.quadro-vaga.colunas.entrevista')
            ->assertJsonPath('redirect', null);

        $anotacao = Anotacao::query()->sole();
        $this->assertSame('Boa comunicação na entrevista.', $anotacao->texto);
        $this->assertSame($this->vaga->id, $anotacao->vaga_id);
        $this->assertSame($this->recrutador->id, $anotacao->user_id);
        $this->assertSame(EtapaSelecao::from($etapa), Participacao::query()->sole()->etapa);
        $movimentacao = MovimentacaoEtapa::query()->latest('id')->first();
        $this->assertSame(EtapaSelecao::Entrevista, $movimentacao->de);
        $this->assertSame(EtapaSelecao::from($etapa), $movimentacao->para);
        $this->assertSame($this->recrutador->id, $movimentacao->user_id);
    }

    public function test_anotacao_no_quadro_pode_manter_entrevista_sem_nova_movimentacao(): void
    {
        $this->moverParaEntrevista();

        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Aguardando segunda conversa.', 'etapa' => ''], json: true)
            ->assertOk()->assertJsonPath('message', 'Anotação registrada. Candidato mantido em entrevista.')
            ->assertJsonPath('data.quadro-vaga.colunas.entrevista.0.codigo', $this->candidato->codigo);

        $this->assertSame(1, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Entrevista, Participacao::query()->sole()->etapa);
        $this->assertSame(1, MovimentacaoEtapa::query()->count());
    }

    public function test_anotacao_no_quadro_por_html_redireciona_ao_quadro(): void
    {
        $this->moverParaEntrevista();

        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Entrevista concluída.', 'etapa' => 'aprovado'])
            ->assertRedirect(route('vagas.show', $this->vaga))->assertSessionHasNoErrors();

        $this->assertSame(1, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Aprovado, Participacao::query()->sole()->etapa);
    }

    #[TestWith(['triagem'])]
    #[TestWith(['entrevista'])]
    #[TestWith(['contratado'])]
    public function test_anotacao_no_quadro_com_destino_invalido_responde_422_sem_salvar(string $etapa): void
    {
        $this->moverParaEntrevista();

        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Entrevista.', 'etapa' => $etapa], json: true)
            ->assertUnprocessable()->assertJsonValidationErrors(['etapa' => 'Escolha manter em entrevista, aprovar ou reprovar.']);

        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Entrevista, Participacao::query()->sole()->etapa);
        $this->assertSame(1, MovimentacaoEtapa::query()->count());
    }

    public function test_anotacao_invalida_no_quadro_nao_aprova_o_candidato(): void
    {
        $this->moverParaEntrevista();

        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => '', 'etapa' => 'aprovado'], json: true)
            ->assertUnprocessable()->assertJsonValidationErrors(['texto' => 'Escreva a anotação antes de registrar.']);

        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Entrevista, Participacao::query()->sole()->etapa);
        $this->assertSame(1, MovimentacaoEtapa::query()->count());
    }

    public function test_anotacao_no_quadro_em_triagem_responde_403_sem_revelar_identidade(): void
    {
        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Tentativa.', 'etapa' => 'aprovado'], json: true)
            ->assertForbidden()->assertDontSee($this->candidato->nome);

        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(0, Participacao::query()->count());
    }

    public function test_etapa_alterada_enquanto_modal_estava_aberta_responde_422_sem_anotar(): void
    {
        $this->moverParaEntrevista();
        $this->patch(route('vagas.candidatos.etapa', [$this->vaga, $this->candidato->codigo]), ['etapa' => 'aprovado'])->assertSessionHasNoErrors();

        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Decisão antiga.', 'etapa' => 'reprovado'], json: true)
            ->assertUnprocessable()->assertJsonValidationErrors('etapa');

        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Aprovado, Participacao::query()->sole()->etapa);
        $this->assertSame(2, MovimentacaoEtapa::query()->count());
    }

    public function test_vaga_encerrada_impede_anotacao_e_decisao_no_quadro(): void
    {
        $this->moverParaEntrevista();
        $this->vaga->update(['status' => 'encerrada', 'encerrada_em' => now()]);

        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Tentativa.', 'etapa' => 'aprovado'], json: true)
            ->assertUnprocessable()->assertJsonValidationErrors('etapa');

        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Entrevista, Participacao::query()->sole()->etapa);
    }

    #[TestWith([['status' => 'desativado']])]
    #[TestWith([['tipo' => 'servidor']])]
    public function test_candidato_que_deixou_de_ser_elegivel_nao_recebe_anotacao_ou_decisao_no_quadro(array $atributos): void
    {
        $this->moverParaEntrevista();
        $this->candidato->update($atributos);

        $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Tentativa.', 'etapa' => 'aprovado'], json: true)
            ->assertUnprocessable()->assertJsonValidationErrors('etapa');

        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Entrevista, Participacao::query()->sole()->etapa);
        $this->assertSame(1, MovimentacaoEtapa::query()->count());
    }

    public function test_falha_ao_gravar_anotacao_desfaz_a_decisao_e_o_historico(): void
    {
        $this->moverParaEntrevista();
        Exceptions::fake();
        Anotacao::creating(static function (): void {
            throw new RuntimeException('Falha simulada ao gravar a anotação.');
        });

        try {
            $this->anotarPelaVaga(['contexto' => 'quadro', 'texto' => 'Não deve ser perdida.', 'etapa' => 'aprovado'], json: true)
                ->assertInternalServerError();
        } finally {
            Anotacao::flushEventListeners();
        }

        Exceptions::assertReported(RuntimeException::class);
        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Entrevista, Participacao::query()->sole()->etapa);
        $this->assertSame(1, MovimentacaoEtapa::query()->count());
    }

    public function test_anotacao_no_perfil_nao_pode_mudar_etapa_fora_do_quadro(): void
    {
        $this->moverParaEntrevista();

        $this->anotarPelaVaga(['texto' => 'Tentativa.', 'etapa' => 'aprovado'], json: true)
            ->assertUnprocessable()->assertJsonValidationErrors('etapa');

        $this->assertSame(0, Anotacao::query()->count());
        $this->assertSame(EtapaSelecao::Entrevista, Participacao::query()->sole()->etapa);
    }

    public function test_seeder_registra_anotacoes_das_entrevistas_da_vaga_de_dados(): void
    {
        Storage::fake('curriculos');
        $this->seed([UsuarioSeeder::class, CompetenciaSeeder::class, CandidatoSeeder::class, VagaSeeder::class, VagaSeeder::class]);

        $dados = Vaga::query()->where('titulo', 'Analista de Dados')->sole();
        $anotacoes = Anotacao::query()->where('vaga_id', $dados->id)->with(['candidato', 'competenciasObservadas', 'autor'])->get();

        $this->assertEqualsCanonicalizing(['Bruno Peixoto', 'Renata Barros'], $anotacoes->pluck('candidato.nome')->all());
        foreach ($anotacoes as $anotacao) {
            $this->assertSame(['Comunicação'], $anotacao->competenciasObservadas->pluck('nome')->all());
            $this->assertSame('recrutador@setic.demo', $anotacao->autor->email);
            $comunicacao = $anotacao->candidato->competencias()->where('nome', 'Comunicação')->first();
            $this->assertSame(OrigemCompetencia::Entrevista->value, $comunicacao->pivot->origem);
        }
    }
}
