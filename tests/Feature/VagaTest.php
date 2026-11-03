<?php

namespace Tests\Feature;

use App\Enums\Area;
use App\Enums\StatusVaga;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use Database\Seeders\CompetenciaSeeder;
use Database\Seeders\UsuarioSeeder;
use Database\Seeders\VagaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Cadastro, edição e encerramento de vagas (US3, FR-015/FR-016) nos modos HTML e JSON (T157).
 */
class VagaTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAGEM_SEM_COMPETENCIAS = 'Selecione ao menos uma competência para a vaga.';

    private User $recrutador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recrutador = User::factory()->create();
        $this->actingAs($this->recrutador);
    }

    private function competencia(string $nome, bool $soft = false): Competencia
    {
        return Competencia::query()->where('nome', $nome)->first()
            ?? ($soft ? Competencia::factory()->soft() : Competencia::factory()->hard())->create(['nome' => $nome]);
    }

    /**
     * Dados no formato do formulário: competencias[ID][peso], com "" para "Não usar".
     *
     * @param  array<string, int|string>  $pesos
     * @return array<string, mixed>
     */
    private function dados(array $pesos = ['Python' => 3, 'SQL' => 2], array $sobrescrever = []): array
    {
        return [
            'titulo' => 'Estagiário de Desenvolvimento',
            'area' => 'desenvolvimento',
            'tipo_candidato' => 'estagiario',
            'descricao' => 'Apoio ao desenvolvimento de sistemas internos.',
            'competencias' => collect($pesos)->mapWithKeys(fn (int|string $peso, string $nome): array => [
                $this->competencia($nome)->id => ['peso' => $peso],
            ])->all(),
            ...$sobrescrever,
        ];
    }

    /** @param array<string, int> $pesos */
    private function vaga(array $pesos = ['Python' => 3], array $atributos = []): Vaga
    {
        $vaga = Vaga::factory()->create($atributos);
        $vaga->competencias()->attach(collect($pesos)
            ->mapWithKeys(fn (int $peso, string $nome): array => [$this->competencia($nome)->id => ['peso' => $peso]])
            ->all());

        return $vaga;
    }

    /** @return array<string, int> */
    private function pesosDa(Vaga $vaga): array
    {
        return $vaga->competencias()->orderBy('nome')->get()
            ->mapWithKeys(fn (Competencia $competencia): array => [$competencia->nome => (int) $competencia->pivot->peso])
            ->all();
    }

    public function test_formulario_de_nova_vaga_separa_hard_e_soft_com_pesos_de_1_a_3(): void
    {
        $python = $this->competencia('Python');
        $this->competencia('Comunicação', soft: true);

        $this->get(route('vagas.create'))
            ->assertOk()
            ->assertSee('Competências técnicas (hard skills)')
            ->assertSee('Competências comportamentais (soft skills)')
            ->assertSeeInOrder(['Não usar', '1 · Desejável', '2 · Importante', '3 · Essencial'])
            ->assertViewHas('dadosTela', fn (string $json): bool => json_decode($json, true)['catalogo']['hard'][0]['id'] === $python->id)
            ->assertSee('data-filtrar-chips', false)
            ->assertSee('data-ajax', false);
    }

    public function test_criar_vaga_valida_redireciona_para_o_quadro_e_fica_aberta(): void
    {
        $resposta = $this->post(route('vagas.store'), $this->dados(['Python' => 3, 'SQL' => 2, 'Git' => '']));

        $vaga = Vaga::query()->sole();
        $resposta->assertRedirect(route('vagas.show', $vaga))->assertSessionHas('status', 'Vaga criada.');
        $this->assertSame(StatusVaga::Aberta, $vaga->status);
        $this->assertSame($this->recrutador->id, $vaga->criada_por);
        $this->assertNull($vaga->encerrada_em);
        $this->assertSame(['Python' => 3, 'SQL' => 2], $this->pesosDa($vaga));

        $this->get(route('vagas.show', $vaga))
            ->assertOk()
            ->assertSee('Estagiário de Desenvolvimento')
            ->assertSee('Aberta')
            ->assertSee('Editar vaga')
            ->assertSee('Encerrar vaga')
            ->assertSee('Atualizar quadro');
    }

    #[TestWith(['vazias'])]
    #[TestWith(['ausentes'])]
    public function test_vaga_sem_competencias_e_rejeitada_e_preserva_o_preenchimento(string $caso): void
    {
        $dados = $caso === 'vazias'
            ? $this->dados(['Python' => '', 'SQL' => ''])
            : $this->dados(sobrescrever: ['competencias' => null]);

        $this->from(route('vagas.create'))->post(route('vagas.store'), $dados)
            ->assertRedirect(route('vagas.create'))
            ->assertSessionHasErrors(['competencias' => self::MENSAGEM_SEM_COMPETENCIAS])
            ->assertSessionHasInput('titulo', 'Estagiário de Desenvolvimento');

        $this->from(route('vagas.create'))->followingRedirects()->post(route('vagas.store'), $dados)
            ->assertOk()
            ->assertSee(self::MENSAGEM_SEM_COMPETENCIAS)
            ->assertViewHas('dadosTela', fn (string $json): bool => json_decode($json, true)['oldInput']['titulo'] === 'Estagiário de Desenvolvimento');

        $this->assertSame(0, Vaga::query()->count());
    }

    #[TestWith([0])]
    #[TestWith([4])]
    #[TestWith(['essencial'])]
    public function test_peso_fora_de_1_2_ou_3_e_rejeitado(int|string $peso): void
    {
        $python = $this->competencia('Python');

        $this->post(route('vagas.store'), $this->dados(['Python' => $peso, 'SQL' => 3]))
            ->assertSessionHasErrors("competencias.{$python->id}.peso");

        $this->assertSame(0, Vaga::query()->count());
    }

    public function test_competencia_repetida_ou_inexistente_e_rejeitada(): void
    {
        $python = $this->competencia('Python');

        $this->post(route('vagas.store'), $this->dados(sobrescrever: ['competencias' => [
            ['id' => $python->id, 'peso' => 3],
            ['id' => $python->id, 'peso' => 2],
        ]]))->assertSessionHasErrors('competencias.1.id');

        $this->post(route('vagas.store'), $this->dados(sobrescrever: ['competencias' => [
            999 => ['peso' => 3],
        ]]))->assertSessionHasErrors('competencias.999.id');

        $this->assertSame(0, Vaga::query()->count());
    }

    public function test_titulo_area_tipo_e_descricao_sao_validados(): void
    {
        $this->post(route('vagas.store'), $this->dados(sobrescrever: [
            'titulo' => '',
            'area' => 'astronomia',
            'tipo_candidato' => 'terceirizado',
            'descricao' => str_repeat('a', 1001),
        ]))->assertSessionHasErrors(['titulo', 'area', 'tipo_candidato', 'descricao']);

        $this->post(route('vagas.store'), $this->dados(sobrescrever: ['titulo' => str_repeat('a', 121)]))
            ->assertSessionHasErrors('titulo');

        $this->assertSame(0, Vaga::query()->count());
    }

    public function test_formulario_de_edicao_traz_dados_e_pesos_atuais(): void
    {
        $vaga = $this->vaga(['Python' => 3, 'SQL' => 1], ['titulo' => 'Analista de Sistemas']);
        $python = $this->competencia('Python');
        $git = $this->competencia('Git');

        $dados = json_decode($this->get(route('vagas.edit', $vaga))
            ->assertOk()
            ->viewData('dadosTela'), true);

        $this->assertSame('Analista de Sistemas', $dados['valores']['titulo']);
        $this->assertSame(3, $dados['valores']['competencias'][$python->id]['peso']);
        $this->assertArrayNotHasKey($git->id, $dados['valores']['competencias']);
    }

    public function test_edicao_atualiza_dados_competencias_e_pesos(): void
    {
        $vaga = $this->vaga(['Python' => 3, 'SQL' => 3]);

        $this->put(route('vagas.update', $vaga), $this->dados(['Python' => 1, 'Git' => 2, 'SQL' => ''], [
            'titulo' => 'Estagiário de Dados',
            'area' => 'dados',
            'tipo_candidato' => 'ambos',
        ]))->assertRedirect(route('vagas.show', $vaga))
            ->assertSessionHas('status', 'Vaga atualizada. O ranking foi recalculado.');

        $vaga->refresh();
        $this->assertSame('Estagiário de Dados', $vaga->titulo);
        $this->assertSame('ambos', $vaga->tipo_candidato->value);
        $this->assertSame(['Git' => 2, 'Python' => 1], $this->pesosDa($vaga));
    }

    public function test_edicao_invalida_nao_altera_a_vaga(): void
    {
        $vaga = $this->vaga(['Python' => 3]);

        $this->put(route('vagas.update', $vaga), $this->dados(['Python' => ''], ['titulo' => 'Outro título']))
            ->assertSessionHasErrors('competencias');

        $this->assertNotSame('Outro título', $vaga->refresh()->titulo);
        $this->assertSame(['Python' => 3], $this->pesosDa($vaga));
    }

    public function test_encerrar_define_status_e_data_e_mantem_o_quadro_para_consulta(): void
    {
        $vaga = $this->vaga();
        $candidato = Candidato::factory()->create(['tipo' => 'estagiario']);
        $vaga->update(['tipo_candidato' => 'estagiario']);

        $this->patch(route('vagas.status', $vaga))
            ->assertRedirect(route('vagas.show', $vaga))
            ->assertSessionHas('status', 'Vaga encerrada. O quadro fica disponível para consulta.');

        $vaga->refresh();
        $this->assertSame(StatusVaga::Encerrada, $vaga->status);
        $this->assertNotNull($vaga->encerrada_em);

        $this->get(route('vagas.show', $vaga))
            ->assertOk()
            ->assertSee('Vaga encerrada — somente consulta')
            ->assertSee($candidato->codigo)
            ->assertViewHas('dadosTela', fn (string $json): bool => json_decode($json, true)['vaga']['aberta'] === false)
            ->assertDontSee(route('vagas.edit', $vaga));
    }

    public function test_encerrar_de_novo_mantem_a_data_original(): void
    {
        $vaga = $this->vaga(atributos: ['status' => 'encerrada', 'encerrada_em' => now()->subWeek()]);
        $data = $vaga->encerrada_em->toDateTimeString();

        $this->patch(route('vagas.status', $vaga))->assertRedirect(route('vagas.show', $vaga));

        $this->assertSame($data, $vaga->refresh()->encerrada_em->toDateTimeString());
    }

    public function test_vaga_encerrada_nao_pode_ser_editada(): void
    {
        $vaga = $this->vaga(['Python' => 3], ['status' => 'encerrada', 'encerrada_em' => now()]);

        $this->get(route('vagas.edit', $vaga))
            ->assertRedirect(route('vagas.show', $vaga))
            ->assertSessionHasErrors('vaga');

        $this->put(route('vagas.update', $vaga), $this->dados(['Python' => 1]))->assertSessionHasErrors('vaga');
        $this->putJson(route('vagas.update', $vaga), $this->dados(['Python' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vaga');

        $this->assertSame(['Python' => 3], $this->pesosDa($vaga));
    }

    public function test_lista_mostra_status_tipo_aceito_e_candidatos_elegiveis(): void
    {
        Candidato::factory()->count(2)->estagiario()->create();
        Candidato::factory()->servidor()->create();
        Candidato::factory()->estagiario()->desativado()->create();
        $estagio = $this->vaga(['Python' => 3], ['titulo' => 'Vaga de estágio', 'tipo_candidato' => 'estagiario']);
        $this->vaga(['Python' => 3], ['titulo' => 'Vaga para todos', 'tipo_candidato' => 'ambos']);
        $this->vaga(['Python' => 3], ['titulo' => 'Vaga encerrada', 'tipo_candidato' => 'servidor', 'status' => 'encerrada', 'encerrada_em' => now()]);

        $this->get(route('vagas.index'))
            ->assertOk()
            ->assertViewHas('dadosTela', function (string $json): bool {
                $vagas = collect(json_decode($json, true)['vagas'])->keyBy('titulo');

                return $vagas['Vaga de estágio']['totalElegiveis'] === 2
                    && $vagas['Vaga para todos']['totalElegiveis'] === 3
                    && $vagas['Vaga encerrada']['totalElegiveis'] === 1;
            })
            ->assertSeeInOrder(['Vaga de estágio', 'Estagiários'])
            ->assertSeeInOrder(['Vaga para todos', 'Servidores e estagiários'])
            ->assertSeeInOrder(['Vaga encerrada', 'Encerrada'])
            ->assertSee(route('vagas.show', $estagio))
            ->assertSee('Nova vaga');
    }

    public function test_lista_traz_a_cor_da_area_e_filtra_apenas_areas_com_vagas(): void
    {
        $this->vaga(atributos: ['titulo' => 'Vaga de redes', 'area' => Area::Infraestrutura]);
        $this->vaga(atributos: ['titulo' => 'Vaga de sistemas', 'area' => Area::Desenvolvimento]);
        $this->vaga(atributos: ['titulo' => 'Outra de sistemas', 'area' => Area::Desenvolvimento]);

        $dados = json_decode($this->get(route('vagas.index'))->assertOk()->viewData('dadosTela'), true);

        $vagas = collect($dados['vagas'])->keyBy('titulo');
        $this->assertSame('infraestrutura', $vagas['Vaga de redes']['areaValor']);
        $this->assertSame(Area::Infraestrutura->cor(), $vagas['Vaga de redes']['cor']);
        $this->assertSame(Area::Desenvolvimento->cor(), $vagas['Vaga de sistemas']['cor']);
        $this->assertSame([
            ['valor' => 'desenvolvimento', 'rotulo' => 'Desenvolvimento', 'cor' => Area::Desenvolvimento->cor()],
            ['valor' => 'infraestrutura', 'rotulo' => 'Infraestrutura e Redes', 'cor' => Area::Infraestrutura->cor()],
        ], $dados['areas']);
    }

    public function test_lista_vazia_convida_a_criar_a_primeira_vaga(): void
    {
        $this->get(route('vagas.index'))
            ->assertOk()
            ->assertSee('Nenhuma vaga cadastrada')
            ->assertSee(route('vagas.create'));
    }

    public function test_visitante_nao_acessa_as_vagas(): void
    {
        $vaga = $this->vaga();
        $this->app['auth']->forgetGuards();

        $this->get(route('vagas.index'))->assertRedirect(route('login'));
        $this->get(route('vagas.create'))->assertRedirect(route('login'));
        $this->post(route('vagas.store'), $this->dados())->assertRedirect(route('login'));
        $this->patch(route('vagas.status', $vaga))->assertRedirect(route('login'));
        $this->postJson(route('vagas.store'), $this->dados())->assertUnauthorized();
        $this->assertSame(StatusVaga::Aberta, $vaga->refresh()->status);
        $this->assertSame(1, Vaga::query()->count());
    }

    public function test_criar_em_json_devolve_redirect_para_o_quadro(): void
    {
        $resposta = $this->postJson(route('vagas.store'), $this->dados());

        $vaga = Vaga::query()->sole();
        $resposta->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'message' => 'Vaga criada.',
                'fragments' => [],
                'redirect' => route('vagas.show', $vaga),
            ]);
    }

    public function test_criar_em_json_invalido_retorna_422_sem_gravar(): void
    {
        $this->postJson(route('vagas.store'), $this->dados(['Python' => '', 'SQL' => ''], ['titulo' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['titulo', 'competencias'])
            ->assertJsonPath('errors.competencias.0', self::MENSAGEM_SEM_COMPETENCIAS);

        $this->assertSame(0, Vaga::query()->count());
    }

    public function test_editar_em_json_devolve_redirect_e_erro_nao_altera_a_vaga(): void
    {
        $vaga = $this->vaga(['Python' => 3]);
        $python = $this->competencia('Python');

        $this->putJson(route('vagas.update', $vaga), $this->dados(['Python' => 9]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors("competencias.{$python->id}.peso");
        $this->assertSame(['Python' => 3], $this->pesosDa($vaga));

        $this->putJson(route('vagas.update', $vaga), $this->dados(['Python' => 2]))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Vaga atualizada. O ranking foi recalculado.',
                'fragments' => [],
                'redirect' => route('vagas.show', $vaga),
            ]);
        $this->assertSame(['Python' => 2], $this->pesosDa($vaga));
    }

    public function test_encerrar_em_json_devolve_o_quadro_somente_para_consulta(): void
    {
        $vaga = $this->vaga(['Python' => 3], ['tipo_candidato' => 'estagiario']);
        $candidato = Candidato::factory()->estagiario()->create(['nome' => 'Otávio Brandão', 'email' => 'otavio@exemplo.com.br']);

        $resposta = $this->patchJson(route('vagas.status', $vaga))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('message', 'Vaga encerrada. O quadro fica disponível para consulta.')
            ->assertJsonPath('redirect', null);

        $resposta->assertJsonPath('fragments', [])
            ->assertJsonPath('data.quadro-vaga.vaga.aberta', false)
            ->assertJsonPath('data.quadro-vaga.vaga.editarUrl', null)
            ->assertJsonPath('data.quadro-vaga.colunas.triagem.0.codigo', $candidato->codigo)
            ->assertJsonPath('data.quadro-vaga.colunas.triagem.0.nome', null);
        $this->assertStringNotContainsString('Otávio Brandão', $resposta->getContent());
        $this->assertStringNotContainsString('otavio@exemplo.com.br', $resposta->getContent());
        $this->assertSame(StatusVaga::Encerrada, $vaga->refresh()->status);
    }

    public function test_dados_iniciais_e_atualizacao_do_quadro_tem_o_mesmo_contrato_sem_html(): void
    {
        $vaga = $this->vaga(['Python' => 3], ['tipo_candidato' => 'estagiario']);
        Candidato::factory()->estagiario()->create();

        $pagina = $this->get(route('vagas.show', $vaga))->assertOk();
        $inicial = json_decode($pagina->viewData('dadosTela'), true);
        unset($inicial['errors']);
        $json = $this->getJson(route('vagas.show', $vaga))->assertOk()
            ->assertJsonPath('fragments', [])
            ->assertJsonPath('data.quadro-vaga', $inicial);

        $this->assertStringNotContainsString('<article', $json->getContent());
        $this->assertStringNotContainsString('<form', $json->getContent());
    }

    public function test_dados_iniciais_escapam_fechamento_de_script_sem_alterar_o_texto(): void
    {
        $ataque = '</script><script>alert("xss")</script>';
        $vaga = $this->vaga(atributos: ['titulo' => $ataque, 'descricao' => $ataque]);

        $pagina = $this->get(route('vagas.show', $vaga))->assertOk();
        $dados = json_decode($pagina->viewData('dadosTela'), true);

        $this->assertSame($ataque, $dados['vaga']['titulo']);
        $this->assertSame($ataque, $dados['vaga']['descricao']);
        $pagina->assertDontSee($ataque, false);
        $this->assertStringNotContainsString('</script>', $pagina->viewData('dadosTela'));
    }

    public function test_formulario_recupera_apenas_campos_permitidos_apos_validacao(): void
    {
        $python = $this->competencia('Python');
        $this->withSession(['_old_input' => [
            '_token' => 'token-que-nao-deve-ir-para-o-json',
            'titulo' => 'Tentativa de edição',
            'competencias' => [$python->id => ['peso' => '']],
            'energia' => ['dinamico_analitico' => ''],
            'campo_extra' => 'nao-expor',
        ]]);

        $pagina = $this->get(route('vagas.create'))->assertOk();
        $dados = json_decode($pagina->viewData('dadosTela'), true);

        $this->assertSame('Tentativa de edição', $dados['oldInput']['titulo']);
        $this->assertSame('', $dados['oldInput']['competencias'][$python->id]['peso']);
        $this->assertSame('', $dados['oldInput']['energia']['dinamico_analitico']);
        $this->assertArrayNotHasKey('_token', $dados['oldInput']);
        $this->assertArrayNotHasKey('campo_extra', $dados['oldInput']);
    }

    public function test_seeder_cria_tres_vagas_abertas_sem_a_vaga_do_pitch(): void
    {
        $this->seed([UsuarioSeeder::class, CompetenciaSeeder::class, VagaSeeder::class, VagaSeeder::class]);

        $vagas = Vaga::query()->orderBy('titulo')->get();

        $this->assertSame(
            ['Analista de Dados', 'Analista de Suporte Técnico', 'Estagiário de Infraestrutura'],
            $vagas->pluck('titulo')->all(),
        );
        $this->assertTrue($vagas->every(fn (Vaga $vaga): bool => $vaga->status === StatusVaga::Aberta));
        $this->assertSame(
            ['servidor', 'ambos', 'estagiario'],
            $vagas->sortBy('id')->map(fn (Vaga $vaga): string => $vaga->tipo_candidato->value)->values()->all(),
        );
        $this->assertSame(
            ['Inglês intermediário' => 1, 'Pensamento analítico' => 1, 'Power BI' => 3, 'Python' => 2, 'SQL' => 3],
            $this->pesosDa($vagas->firstWhere('titulo', 'Analista de Dados')),
        );
        $this->assertSame(
            ['Linux' => 3, 'Redes de Computadores' => 3, 'Suporte a Hardware' => 2, 'Trabalho em equipe' => 1],
            $this->pesosDa($vagas->firstWhere('titulo', 'Estagiário de Infraestrutura')),
        );
        $this->assertSame(
            ['Atendimento ao público' => 3, 'Comunicação' => 2, 'ITIL' => 2, 'Proatividade' => 1, 'Windows' => 3],
            $this->pesosDa($vagas->firstWhere('titulo', 'Analista de Suporte Técnico')),
        );
        $this->assertFalse(Vaga::query()->where('titulo', 'Estagiário de Desenvolvimento')->exists());
        $this->assertSame(
            User::query()->where('email', 'recrutador@setic.demo')->value('id'),
            $vagas->first()->criada_por,
        );
    }
}
