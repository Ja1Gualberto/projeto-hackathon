<?php

namespace Tests\Feature;

use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use App\Services\PainelService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Rota;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

/**
 * Contrato transversal do modo AJAX (T167, contracts/rotas-web.md → "Respostas de erro"):
 * autenticação, proteção contra requisição forjada, erros controlados, cache privado e
 * autorização/anonimização iguais em HTML e em fragmentos JSON.
 */
class AjaxSegurancaTest extends TestCase
{
    use RefreshDatabase;

    private const NOME = 'Fernanda Quintela';

    private const EMAIL = 'fernanda.quintela@exemplo.com.br';

    private const TELEFONE = '(69) 98888-7777';

    private const TOKEN = 'token-de-sessao-valido';

    private const REGIOES = [
        'resultados-candidatos', 'catalogo-competencias', 'quadro-vaga', 'perfil-na-vaga', 'historico-anotacoes',
        'competencias-candidato', 'situacao-candidato', 'secao-lgpd', 'secao-energia',
    ];

    private Candidato $candidato;

    private Vaga $vaga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->candidato = Candidato::factory()->estagiario()->create([
            'nome' => self::NOME,
            'email' => self::EMAIL,
            'telefone' => self::TELEFONE,
            'links' => ['https://github.com/fernanda-demo'],
        ]);
        $python = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $this->candidato->competencias()->attach($python->id, ['origem' => 'curriculo']);
        $this->vaga = Vaga::factory()->paraEstagiarios()->create();
        $this->vaga->competencias()->attach($python->id, ['peso' => 3]);
    }

    protected function tearDown(): void
    {
        PreventRequestForgery::flushState();

        parent::tearDown();
    }

    /**
     * O middleware real ignora a verificação quando app()->runningUnitTests(); fora de "testing",
     * o que roda aqui é exatamente o que roda em produção, sem desligar nada.
     */
    private function ativarProtecaoContraForja(): void
    {
        $this->app['env'] = 'local';
    }

    private function comSessaoValida(): static
    {
        return $this->actingAs(User::factory()->create())->withSession(['_token' => self::TOKEN]);
    }

    /**
     * Todas as rotas de escrita da aplicação, com parâmetros que atendem às restrições da rota.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function rotasDeEscrita(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (Rota $rota): bool => in_array('web', $rota->gatherMiddleware(), true) && $rota->getName() !== 'login.store')
            ->flatMap(fn (Rota $rota): array => collect($rota->methods())
                ->reject(fn (string $metodo): bool => in_array($metodo, ['GET', 'HEAD', 'OPTIONS'], true))
                ->map(fn (string $metodo): array => [$metodo, preg_replace(
                    ['/\{importacao\}/', '/\{candidato\}(?=.*)/', '/\{vaga\}/'],
                    ['11111111-1111-4111-8111-111111111111', str_contains($rota->uri(), 'vagas/') ? 'CAND-0001' : '1', '1'],
                    $rota->uri(),
                )])
                ->all())
            ->values()
            ->all();
    }

    /** @return array<string, array{0: string}> */
    public static function rotasDeLeitura(): array
    {
        return [
            'painel' => ['painel'],
            'candidatos' => ['candidatos.index'],
            'importar' => ['importacoes.create'],
            'vagas' => ['vagas.index'],
            'nova vaga' => ['vagas.create'],
        ];
    }

    #[DataProvider('rotasDeLeitura')]
    public function test_visitante_recebe_401_em_json_e_redirecionamento_em_html(string $rota): void
    {
        $this->getJson(route($rota))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Sua sessão expirou. Entre novamente para continuar.'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->get(route($rota))->assertRedirect(route('login'));
    }

    public function test_sessao_expirada_em_escrita_nao_grava_e_distingue_json_de_html(): void
    {
        $this->ativarProtecaoContraForja();
        $rota = route('candidatos.anotacoes.store', $this->candidato);

        // O navegador prova a mesma origem e a sessão acabou: quem responde é a autenticação.
        $this->postJson($rota, ['texto' => 'Sem sessão.'], ['Sec-Fetch-Site' => 'same-origin'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Sua sessão expirou. Entre novamente para continuar.']);
        $this->post($rota, ['texto' => 'Sem sessão.'], ['Sec-Fetch-Site' => 'same-origin'])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_todas_as_rotas_de_escrita_recusam_envio_sem_token(): void
    {
        $this->ativarProtecaoContraForja();
        $this->comSessaoValida();
        $rotas = $this->rotasDeEscrita();

        // Guarda contra o filtro acima deixar de enxergar as rotas e o teste passar sem verificar nada.
        $this->assertGreaterThanOrEqual(15, count($rotas));

        foreach ($rotas as [$metodo, $uri]) {
            $this->json($metodo, '/'.$uri, ['qualquer' => 'dado'])
                ->assertStatus(419)
                ->assertExactJson(['message' => 'Sua sessão expirou. Atualize a página antes de continuar.'])
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_login_tambem_exige_token(): void
    {
        $this->ativarProtecaoContraForja();

        $this->withSession(['_token' => self::TOKEN])
            ->post(route('login.store'), ['email' => 'recrutador@setic.demo', 'password' => 'demo1234'])
            ->assertStatus(419);
    }

    public function test_token_invalido_nao_grava_e_o_html_mostra_pagina_amigavel(): void
    {
        $this->ativarProtecaoContraForja();
        $this->comSessaoValida();
        $rota = route('candidatos.anotacoes.store', $this->candidato);

        $this->postJson($rota, ['texto' => 'Forjada.', '_token' => 'token-errado'])->assertStatus(419);
        $this->post($rota, ['texto' => 'Forjada.', '_token' => 'token-errado'])
            ->assertStatus(419)
            ->assertSee('Sua sessão expirou')
            ->assertSee('não foi processado')
            ->assertDontSee('Page Expired');

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_token_valido_passa_pelo_campo_e_pelo_cabecalho(): void
    {
        $this->ativarProtecaoContraForja();
        $this->comSessaoValida();
        $rota = route('candidatos.anotacoes.store', $this->candidato);

        $this->postJson($rota, ['texto' => 'Pelo campo.', '_token' => self::TOKEN])->assertOk();
        $this->postJson($rota, ['texto' => 'Pelo cabeçalho.'], ['X-CSRF-TOKEN' => self::TOKEN])->assertOk();

        $this->assertSame(2, Anotacao::query()->count());
    }

    #[TestWith(['cross-site'])]
    #[TestWith(['same-site'])]
    #[TestWith(['none'])]
    public function test_origem_diferente_sem_token_e_recusada_sem_gravar(string $origem): void
    {
        $this->ativarProtecaoContraForja();
        $this->comSessaoValida();

        $this->postJson(route('candidatos.anotacoes.store', $this->candidato), ['texto' => 'Forjada.'], ['Sec-Fetch-Site' => $origem])
            ->assertStatus(419);

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_mesma_origem_declarada_pelo_navegador_e_aceita(): void
    {
        $this->ativarProtecaoContraForja();
        $this->comSessaoValida();

        $this->postJson(route('candidatos.anotacoes.store', $this->candidato), ['texto' => 'Da própria aplicação.'], ['Sec-Fetch-Site' => 'same-origin'])
            ->assertOk();

        $this->assertSame(1, Anotacao::query()->count());
    }

    public function test_verificacao_estrita_de_origem_responde_403_controlado(): void
    {
        $this->ativarProtecaoContraForja();
        PreventRequestForgery::useOriginOnly();
        $this->comSessaoValida();

        $this->postJson(route('candidatos.anotacoes.store', $this->candidato), ['texto' => 'Forjada.', '_token' => self::TOKEN], ['Sec-Fetch-Site' => 'cross-site'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Você não tem permissão para acessar este recurso.'])
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_recursos_inexistentes_respondem_404_sem_detalhes_em_json(): void
    {
        config(['app.debug' => true]);
        $this->actingAs(User::factory()->create());

        foreach ([
            '/candidatos/999999',
            '/vagas/999999',
            '/vagas/'.$this->vaga->id.'/candidatos/CAND-9999',
            '/candidatos/importar/11111111-1111-4111-8111-111111111111',
            '/rota-que-nao-existe',
        ] as $uri) {
            $this->getJson($uri)
                ->assertNotFound()
                ->assertJsonStructure(['message'])
                ->assertJsonMissingPath('exception')
                ->assertJsonMissingPath('trace')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_metodo_nao_permitido_nao_expoe_detalhes_mesmo_com_debug(): void
    {
        config(['app.debug' => true]);
        $this->actingAs(User::factory()->create());

        $this->deleteJson(route('vagas.index'))
            ->assertStatus(405)
            ->assertExactJson(['message' => 'Esta ação não está disponível para este recurso.']);
    }

    public function test_erro_interno_em_json_nao_expoe_a_excecao(): void
    {
        config(['app.debug' => true]);
        $this->actingAs(User::factory()->create());
        $this->app->bind(PainelService::class, fn (): never => throw new RuntimeException('Detalhe privado do servidor'));

        $resposta = $this->getJson(route('painel'))->assertStatus(500)
            ->assertExactJson(['message' => 'Não foi possível concluir a operação. Tente consultar o estado antes de continuar.']);

        $this->assertStringNotContainsString('Detalhe privado', $resposta->getContent());
    }

    public function test_erro_interno_em_html_mostra_pagina_amigavel_sem_a_excecao(): void
    {
        config(['app.debug' => false]);
        $this->actingAs(User::factory()->create());
        $this->app->bind(PainelService::class, fn (): never => throw new RuntimeException('Detalhe privado do servidor'));

        $this->get(route('painel'))
            ->assertStatus(500)
            ->assertSee('Não foi possível concluir a operação')
            ->assertDontSee('Detalhe privado');
    }

    public function test_excesso_de_tentativas_de_login_tem_mensagem_em_portugues(): void
    {
        for ($tentativa = 0; $tentativa < 5; $tentativa++) {
            $this->post(route('login.store'), ['email' => 'alguem@exemplo.com', 'password' => 'errada']);
        }

        $this->post(route('login.store'), ['email' => 'alguem@exemplo.com', 'password' => 'errada'])
            ->assertTooManyRequests()
            ->assertSee('Muitas tentativas seguidas')
            ->assertDontSee('Too Many');
        $this->postJson(route('login.store'), ['email' => 'alguem@exemplo.com', 'password' => 'errada'])
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Muitas tentativas seguidas. Aguarde um minuto e tente novamente.']);
    }

    public function test_paginas_com_dados_pessoais_nao_ficam_em_cache(): void
    {
        $this->actingAs(User::factory()->create());
        $this->patch(route('vagas.candidatos.etapa', [$this->vaga, $this->candidato->codigo]), ['etapa' => 'entrevista']);

        foreach ([
            route('candidatos.index'),
            route('candidatos.show', $this->candidato),
            route('candidatos.edit', $this->candidato),
            route('vagas.show', $this->vaga),
            route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]),
        ] as $url) {
            $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_escritas_em_json_seguem_o_envelope_e_nao_ficam_em_cache(): void
    {
        $this->actingAs(User::factory()->create());
        $mover = route('vagas.candidatos.etapa', [$this->vaga, $this->candidato->codigo]);

        $respostas = [
            $this->patchJson($mover, ['etapa' => 'entrevista', 'contexto' => 'quadro']),
            $this->patchJson($mover, ['etapa' => 'aprovado', 'contexto' => 'perfil']),
            $this->postJson(route('candidatos.anotacoes.store', $this->candidato), ['texto' => 'Anotação válida.']),
            $this->patchJson(route('candidatos.status', $this->candidato), ['status' => 'desativado']),
            $this->patchJson(route('candidatos.status', $this->candidato), ['status' => 'ativo']),
            $this->putJson(route('candidatos.energia.update', $this->candidato), [
                'dinamico_analitico' => 2, 'individual_equipe' => 3, 'execucao_planejamento' => 4,
            ]),
            $this->getJson(route('candidatos.index')),
            $this->getJson(route('vagas.show', $this->vaga)),
            $this->patchJson(route('vagas.status', $this->vaga)),
        ];

        foreach ($respostas as $resposta) {
            $resposta->assertOk()
                ->assertHeader('Cache-Control', 'no-store, private')
                ->assertHeader('Content-Type', 'application/json');
            $envelope = $resposta->json();

            $this->assertEqualsCanonicalizing(['message', 'fragments', 'redirect'], array_keys($envelope));
            $this->assertNotSame([], $envelope['fragments']);
            $this->assertSame([], array_diff(array_keys($envelope['fragments']), self::REGIOES));
        }
    }

    public function test_parametros_da_requisicao_nao_escolhem_a_regiao_devolvida(): void
    {
        $this->actingAs(User::factory()->create());

        $envelope = $this->getJson(route('vagas.show', $this->vaga).'?regiao=secao-lgpd&fragments[]=secao-lgpd&view=candidatos.show')
            ->assertOk()->json();

        $this->assertSame(['quadro-vaga'], array_keys($envelope['fragments']));
    }

    public function test_validacao_em_json_responde_422_sem_html_nem_cache(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([
            $this->postJson(route('candidatos.anotacoes.store', $this->candidato), ['texto' => '']),
            $this->postJson(route('vagas.store'), []),
            $this->putJson(route('candidatos.update', $this->candidato), ['nome' => '']),
            $this->deleteJson(route('candidatos.destroy', $this->candidato), []),
            $this->putJson(route('candidatos.energia.update', $this->candidato), ['dinamico_analitico' => 9]),
            $this->patchJson(route('vagas.candidatos.etapa', [$this->vaga, $this->candidato->codigo]), ['etapa' => 'inexistente']),
        ] as $resposta) {
            $resposta->assertUnprocessable()
                ->assertJsonStructure(['message', 'errors'])
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertStringNotContainsString('<', $resposta->getContent());
        }

        $this->assertDatabaseHas('candidatos', ['id' => $this->candidato->id, 'nome' => self::NOME]);
    }

    /**
     * @param  list<TestResponse>  $respostas
     */
    private function assertSemIdentidadeNoCorpo(array $respostas): void
    {
        foreach ($respostas as $resposta) {
            // Decodifica o JSON: um nome com acento viaja escapado no corpo cru (é).
            $corpo = html_entity_decode(json_encode($resposta->json(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');

            foreach ([self::NOME, self::EMAIL, self::TELEFONE, 'github.com/fernanda-demo', route('candidatos.show', $this->candidato)] as $proibido) {
                $this->assertStringNotContainsString($proibido, $corpo);
            }
        }
    }

    public function test_fragmentos_da_triagem_nao_trazem_identidade_nem_na_mensagem(): void
    {
        $this->actingAs(User::factory()->create());
        $perfil = route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]);
        $mover = route('vagas.candidatos.etapa', [$this->vaga, $this->candidato->codigo]);

        $this->assertSemIdentidadeNoCorpo([$this->getJson(route('vagas.show', $this->vaga))->assertOk()]);

        $revelado = $this->patchJson($mover, ['etapa' => 'entrevista', 'contexto' => 'perfil'])->assertOk();
        $this->assertStringContainsString(self::NOME, html_entity_decode($revelado->json('fragments.perfil-na-vaga')));

        $this->assertSemIdentidadeNoCorpo([
            $this->patchJson($mover, ['etapa' => 'triagem', 'contexto' => 'perfil'])->assertOk(),
            $this->patchJson($mover, ['etapa' => 'triagem', 'contexto' => 'quadro'])->assertOk(),
            $this->getJson(route('vagas.show', $this->vaga))->assertOk(),
        ]);
        $this->get($perfil)->assertOk()->assertDontSee(self::NOME)->assertDontSee(self::EMAIL);
    }

    public function test_formato_da_resposta_nao_muda_a_autorizacao_na_triagem(): void
    {
        $this->actingAs(User::factory()->create());
        $rota = route('vagas.candidatos.anotacoes.store', [$this->vaga, $this->candidato->codigo]);

        $this->post($rota, ['texto' => 'Na triagem.'])->assertForbidden();
        $this->postJson($rota, ['texto' => 'Na triagem.'])
            ->assertForbidden()
            ->assertExactJson(['message' => 'Você não tem permissão para acessar este recurso.']);

        $this->assertSame(0, Anotacao::query()->count());
    }

    public function test_texto_do_usuario_chega_escapado_nos_fragmentos(): void
    {
        $this->actingAs(User::factory()->create());
        $ataque = '<script>alert("xss")</script>';
        $this->candidato->update(['nome' => 'Fulana '.$ataque]);

        $historico = $this->postJson(route('candidatos.anotacoes.store', $this->candidato), ['texto' => $ataque])
            ->assertOk()->json('fragments.historico-anotacoes');
        $resultados = $this->getJson(route('candidatos.index'))->assertOk()->json('fragments.resultados-candidatos');

        foreach ([$historico, $resultados] as $fragmento) {
            $this->assertStringNotContainsString('<script>', $fragmento);
            $this->assertStringContainsString('&lt;script&gt;', $fragmento);
        }
    }

    public function test_paginacao_e_contagem_estao_em_portugues(): void
    {
        $this->actingAs(User::factory()->create());
        Candidato::factory()->estagiario()->count(12)->create();

        $this->get(route('candidatos.index'))
            ->assertOk()
            ->assertSee('13 talentos encontrados')
            ->assertSee('Mostrando')
            ->assertSee('Próxima')
            ->assertDontSee('pagination.')
            ->assertDontSee('Showing');

        $this->get(route('candidatos.index', ['busca' => self::NOME]))
            ->assertOk()
            ->assertSee('1 talento encontrado')
            ->assertDontSee('1 talentos encontrados');
    }
}
