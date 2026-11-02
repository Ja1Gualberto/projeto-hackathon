<?php

namespace Tests\Feature;

use App\DTOs\ItemRankingDTO;
use App\Enums\EtapaSelecao;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use App\Services\Aderencia\RankingVagaService;
use Database\Seeders\CandidatoSeeder;
use Database\Seeders\CompetenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Ranking explicado da vaga (US3, FR-017–FR-020) e o modo JSON do quadro (T157).
 */
class RankingVagaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function competencia(string $nome): Competencia
    {
        return Competencia::query()->where('nome', $nome)->first()
            ?? Competencia::factory()->hard()->create(['nome' => $nome]);
    }

    /** @param array<string, int> $pesos */
    private function vaga(array $pesos, string $tipo = 'estagiario', array $atributos = []): Vaga
    {
        $vaga = Vaga::factory()->create(['tipo_candidato' => $tipo, ...$atributos]);
        $vaga->competencias()->attach(collect($pesos)
            ->mapWithKeys(fn (int $peso, string $nome): array => [$this->competencia($nome)->id => ['peso' => $peso]])
            ->all());

        return $vaga;
    }

    /** @param list<string> $competencias */
    private function candidato(string $tipo, array $competencias, array $atributos = []): Candidato
    {
        $candidato = Candidato::factory()->create(['tipo' => $tipo, ...$atributos]);
        $candidato->competencias()->attach(
            collect($competencias)->map(fn (string $nome): int => $this->competencia($nome)->id)->all(),
            ['origem' => 'curriculo'],
        );

        return $candidato;
    }

    private function quadroJson(Vaga $vaga): TestResponse
    {
        return $this->getJson(route('vagas.show', $vaga));
    }

    public function test_vaga_de_estagio_lista_somente_estagiarios_e_a_de_servidor_somente_servidores(): void
    {
        $estagiario = $this->candidato('estagiario', ['Python']);
        $servidor = $this->candidato('servidor', ['Python']);

        $this->get(route('vagas.show', $this->vaga(['Python' => 3], 'estagiario')))
            ->assertOk()->assertSee($estagiario->codigo)->assertDontSee($servidor->codigo);

        $this->get(route('vagas.show', $this->vaga(['Python' => 3], 'servidor')))
            ->assertOk()->assertSee($servidor->codigo)->assertDontSee($estagiario->codigo);
    }

    public function test_vaga_para_ambos_lista_os_dois_tipos_e_nunca_os_desativados(): void
    {
        $estagiario = $this->candidato('estagiario', ['Python']);
        $servidor = $this->candidato('servidor', []);
        $desativado = $this->candidato('estagiario', ['Python'], ['status' => 'desativado']);

        $this->get(route('vagas.show', $this->vaga(['Python' => 3], 'ambos')))
            ->assertOk()
            ->assertSee($estagiario->codigo)
            ->assertSee($servidor->codigo)
            ->assertDontSee($desativado->codigo)
            ->assertViewHas('dadosTela', fn (string $json): bool => json_decode($json, true)['totalElegiveis'] === 2);
    }

    public function test_ordem_segue_percentual_peso_essencial_data_de_cadastro_e_id(): void
    {
        $this->freezeSecond();
        $vaga = $this->vaga(['Python' => 3, 'Git' => 2, 'Scrum' => 1]);

        $metadeSemEssencial = $this->candidato('estagiario', ['Git', 'Scrum'], ['created_at' => now()->subDays(5)]);
        $metadeComEssencial = $this->candidato('estagiario', ['Python'], ['created_at' => now()->subDays(4)]);
        $completo = $this->candidato('estagiario', ['Python', 'Git', 'Scrum'], ['created_at' => now()]);
        $empateMesmaData = $this->candidato('estagiario', ['Scrum'], ['created_at' => now()]);
        $empateMesmaDataDepois = $this->candidato('estagiario', ['Scrum'], ['created_at' => now()]);
        $empateMaisAntigo = $this->candidato('estagiario', ['Scrum'], ['created_at' => now()->subDay()]);
        $semNada = $this->candidato('estagiario', [], ['created_at' => now()->subYear()]);

        $esperado = [
            $completo, $metadeComEssencial, $metadeSemEssencial,
            $empateMaisAntigo, $empateMesmaData, $empateMesmaDataDepois, $semNada,
        ];
        $codigos = array_map(fn (Candidato $candidato): string => $candidato->codigo, $esperado);

        $ranking = app(RankingVagaService::class)->ranquear($vaga);

        $this->assertSame($codigos, $ranking->map(fn (ItemRankingDTO $item): string => $item->codigo)->all());
        $this->assertSame([100, 50, 50, 17, 17, 17, 0], $ranking->map(fn (ItemRankingDTO $item): int => $item->resultado->percentual)->all());
        $this->get(route('vagas.show', $vaga))->assertOk()->assertSeeInOrder($codigos);
    }

    public function test_cada_item_do_ranking_e_anonimo_e_em_triagem(): void
    {
        $candidato = $this->candidato('estagiario', ['Python']);

        $item = app(RankingVagaService::class)->ranquear($this->vaga(['Python' => 3]))->sole();

        $this->assertSame($candidato->codigo, $item->codigo);
        $this->assertSame(EtapaSelecao::Triagem, $item->etapa);
        $this->assertNull($item->nome);
        $this->assertNull($item->proximidadeEnergia);
        $this->assertSame($candidato->tipo, $item->tipo);
        $this->assertSame($candidato->area, $item->area);
    }

    public function test_todos_os_cards_tem_percentual_e_explicacao(): void
    {
        $vaga = $this->vaga(['Python' => 3, 'SQL' => 3, 'Git' => 2]);
        $this->candidato('estagiario', ['Python', 'SQL', 'Git']);
        $this->candidato('estagiario', ['Python']);
        $this->candidato('estagiario', ['Figma']);

        $resposta = $this->get(route('vagas.show', $vaga))->assertOk();

        $ranking = app(RankingVagaService::class)->ranquear($vaga);
        $this->assertCount(3, $ranking);

        foreach ($ranking as $item) {
            $resposta->assertSee($item->resultado->explicacao());
            $this->quadroJson($vaga)->assertJsonPath('data.quadro-vaga.colunas.triagem', fn (array $itens): bool => collect($itens)->firstWhere('codigo', $item->codigo)['percentual'] === $item->resultado->percentual);
        }

        $resposta->assertSee('100% — tem todas as 3 competências')
            ->assertSee('38% — tem 1 de 3 competências; não identificado: SQL (peso 3), Git (peso 2)')
            ->assertSee('0% — nenhuma competência solicitada foi identificada no currículo');
    }

    public function test_alterar_o_peso_muda_a_ordem_na_proxima_abertura(): void
    {
        $vaga = $this->vaga(['Python' => 3, 'Scrum' => 1]);
        $pythonista = $this->candidato('estagiario', ['Python']);
        $agilista = $this->candidato('estagiario', ['Scrum']);

        $this->get(route('vagas.show', $vaga))->assertSeeInOrder([$pythonista->codigo, $agilista->codigo]);

        $this->put(route('vagas.update', $vaga), [
            'titulo' => $vaga->titulo,
            'area' => $vaga->area->value,
            'tipo_candidato' => 'estagiario',
            'competencias' => [
                $this->competencia('Python')->id => ['peso' => 1],
                $this->competencia('Scrum')->id => ['peso' => 3],
            ],
        ])->assertRedirect(route('vagas.show', $vaga));

        $this->get(route('vagas.show', $vaga))
            ->assertSeeInOrder([$agilista->codigo, $pythonista->codigo])
            ->assertSee('75% — tem 1 de 2 competências; não identificado: Python (peso 1)');
    }

    public function test_candidato_cadastrado_depois_da_vaga_aparece_no_ranking(): void
    {
        $vaga = $this->vaga(['Python' => 3]);
        $this->get(route('vagas.show', $vaga))->assertSee('Nenhum candidato elegível');

        $novo = $this->candidato('estagiario', ['Python']);

        $this->get(route('vagas.show', $vaga))->assertSee($novo->codigo)->assertSee('100% — tem a competência solicitada');
    }

    public function test_cards_de_triagem_nao_expoem_identidade_nem_link_para_o_banco(): void
    {
        $candidato = $this->candidato('estagiario', ['Python'], [
            'nome' => 'Fernanda Quintela',
            'email' => 'fernanda.quintela@exemplo.com.br',
            'telefone' => '(69) 98888-7777',
            'cpf' => '52998224725',
        ]);
        $vaga = $this->vaga(['Python' => 3]);

        $html = $this->get(route('vagas.show', $vaga))->assertOk()->assertSee($candidato->codigo)->getContent();
        $fragmento = json_encode($this->quadroJson($vaga)->assertOk()->json('data.quadro-vaga'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ([$html, $fragmento] as $conteudo) {
            $this->assertStringContainsString($candidato->codigo, $conteudo);

            foreach (['Fernanda Quintela', 'fernanda.quintela@exemplo.com.br', '(69) 98888-7777', '52998224725', route('candidatos.show', $candidato)] as $proibido) {
                $this->assertStringNotContainsString($proibido, $conteudo);
            }
        }
    }

    public function test_quadro_em_json_tem_a_mesma_ordem_e_explicacao_da_pagina(): void
    {
        $vaga = $this->vaga(['Python' => 3, 'SQL' => 3, 'Git' => 2]);
        $candidatos = [
            $this->candidato('estagiario', ['Git']),
            $this->candidato('estagiario', ['Python', 'SQL']),
            $this->candidato('estagiario', ['Python', 'SQL', 'Git']),
        ];

        $resposta = $this->quadroJson($vaga)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('message', null)
            ->assertJsonPath('redirect', null);

        $resposta->assertJsonPath('fragments', []);
        $this->assertSame(['quadro-vaga'], array_keys($resposta->json('data')));
        $itens = $resposta->json('data.quadro-vaga.colunas.triagem');

        $ranking = app(RankingVagaService::class)->ranquear($vaga);
        $ordem = $ranking->flatMap(fn (ItemRankingDTO $item): array => [$item->codigo, $item->resultado->explicacao()])->all();

        $this->assertSame(
            [$candidatos[2]->codigo, $candidatos[1]->codigo, $candidatos[0]->codigo],
            $ranking->map(fn (ItemRankingDTO $item): string => $item->codigo)->all(),
        );
        $this->get(route('vagas.show', $vaga))->assertSeeInOrder($ordem)->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame($ordem, collect($itens)->flatMap(fn (array $item): array => [$item['codigo'], $item['explicacao']])->all());
    }

    public function test_visitante_nao_acessa_o_quadro_em_html_nem_em_json(): void
    {
        $vaga = $this->vaga(['Python' => 3]);
        $this->app['auth']->forgetGuards();

        $this->get(route('vagas.show', $vaga))->assertRedirect(route('login'));
        $this->getJson(route('vagas.show', $vaga))->assertUnauthorized()->assertJsonMissingPath('fragments');
    }

    public function test_vaga_inexistente_responde_404(): void
    {
        $this->get('/vagas/999')->assertNotFound();
        $this->getJson('/vagas/999')->assertNotFound();
    }

    public function test_cenario_c_do_roteiro_com_os_dados_semeados(): void
    {
        Storage::fake('curriculos');
        $this->seed([CompetenciaSeeder::class, CandidatoSeeder::class]);

        $pesos = ['Python' => 3, 'SQL' => 3, 'Git' => 2, 'Inglês básico' => 1, 'Trabalho em equipe' => 1];
        $this->post(route('vagas.store'), [
            'titulo' => 'Estagiário de Desenvolvimento',
            'area' => 'desenvolvimento',
            'tipo_candidato' => 'estagiario',
            'competencias' => collect($pesos)->mapWithKeys(fn (int $peso, string $nome): array => [
                $this->competencia($nome)->id => ['peso' => $peso],
            ])->all(),
        ])->assertRedirect();

        $vaga = Vaga::query()->where('titulo', 'Estagiário de Desenvolvimento')->sole();
        $ordem = [
            'Marina Duarte' => 100, 'Lucas Andrade' => 90, 'Ana Lima' => 90, 'Beatriz Nogueira' => 80,
            'Pedro Valença' => 70, 'Rafael Monteiro' => 60, 'Camila Farias' => 60, 'Vinícius Melo' => 30,
            'Júlia Azevedo' => 30, 'Gabriel Tavares' => 10, 'Isabela Prado' => 10, 'Letícia Campos' => 0,
        ];
        $codigos = collect(array_keys($ordem))
            ->map(fn (string $nome): string => Candidato::query()->where('nome', $nome)->value('codigo'))->all();

        $ranking = app(RankingVagaService::class)->ranquear($vaga);

        $this->assertSame($codigos, $ranking->map(fn (ItemRankingDTO $item): string => $item->codigo)->all());
        $this->assertSame(array_values($ordem), $ranking->map(fn (ItemRankingDTO $item): int => $item->resultado->percentual)->all());

        $this->get(route('vagas.show', $vaga))
            ->assertOk()
            ->assertViewHas('dadosTela', fn (string $json): bool => json_decode($json, true)['totalElegiveis'] === 12)
            ->assertSeeInOrder($codigos)
            ->assertSee('80% — tem 4 de 5 competências; não identificado: Git (peso 2)')
            ->assertDontSee('Bruno Peixoto');
    }
}
