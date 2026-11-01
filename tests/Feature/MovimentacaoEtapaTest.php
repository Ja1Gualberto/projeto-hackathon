<?php

namespace Tests\Feature;

use App\Enums\EtapaSelecao;
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
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Movimentação entre Triagem, Entrevista e Aprovado (US4, FR-021/FR-022/FR-024), nos modos
 * HTML e JSON (T098, T159).
 */
class MovimentacaoEtapaTest extends TestCase
{
    use RefreshDatabase;

    private User $recrutador;

    private Candidato $candidato;

    private Vaga $vaga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recrutador = User::factory()->create(['name' => 'Recrutadora Teste']);
        $this->actingAs($this->recrutador);
        $this->candidato = $this->candidato();
        $this->vaga = $this->vaga();
    }

    private function vaga(array $atributos = []): Vaga
    {
        $vaga = Vaga::factory()->paraEstagiarios()->create($atributos);
        $python = Competencia::query()->firstWhere('nome', 'Python') ?? Competencia::factory()->hard()->create(['nome' => 'Python']);
        $vaga->competencias()->attach($python, ['peso' => 3]);

        return $vaga;
    }

    private function candidato(array $atributos = []): Candidato
    {
        return Candidato::factory()->estagiario()->create($atributos);
    }

    private function mover(string $etapa, ?Candidato $candidato = null, ?Vaga $vaga = null, array $extra = [], bool $json = false): TestResponse
    {
        $rota = route('vagas.candidatos.etapa', [$vaga ?? $this->vaga, ($candidato ?? $this->candidato)->codigo]);
        $dados = ['etapa' => $etapa, ...$extra];

        return $json ? $this->patchJson($rota, $dados) : $this->patch($rota, $dados);
    }

    private function etapa(?Vaga $vaga = null, ?Candidato $candidato = null): ?EtapaSelecao
    {
        return Participacao::query()
            ->where('vaga_id', ($vaga ?? $this->vaga)->id)
            ->where('candidato_id', ($candidato ?? $this->candidato)->id)
            ->first()?->etapa;
    }

    public function test_mover_cria_a_participacao_e_registra_quem_moveu(): void
    {
        $this->assertSame(0, Participacao::query()->count());

        $this->mover('entrevista')
            ->assertRedirect(route('vagas.show', $this->vaga))
            ->assertSessionHas('status', $this->candidato->nome.' está agora em Entrevista.');

        $this->assertSame(EtapaSelecao::Entrevista, $this->etapa());
        $movimentacao = MovimentacaoEtapa::query()->sole();
        $this->assertSame($this->recrutador->id, $movimentacao->user_id);
        $this->assertSame(EtapaSelecao::Triagem, $movimentacao->de);
        $this->assertSame(EtapaSelecao::Entrevista, $movimentacao->para);
        $this->assertNotNull($movimentacao->created_at);

        $this->mover('aprovado');

        $this->assertSame(EtapaSelecao::Aprovado, $this->etapa());
        $this->assertSame(1, Participacao::query()->count());
        $ultima = MovimentacaoEtapa::query()->latest('id')->first();
        $this->assertSame([EtapaSelecao::Entrevista, EtapaSelecao::Aprovado], [$ultima->de, $ultima->para]);
    }

    public function test_mover_para_a_mesma_etapa_nao_registra_nova_movimentacao(): void
    {
        $this->mover('triagem')->assertSessionHasNoErrors();
        $this->assertSame(0, MovimentacaoEtapa::query()->count());

        $this->mover('entrevista');
        $this->mover('entrevista');

        $this->assertSame(1, MovimentacaoEtapa::query()->count());
    }

    public function test_etapa_pertence_a_cada_vaga(): void
    {
        $outra = $this->vaga();
        $this->mover('entrevista', vaga: $outra);

        $this->mover('aprovado');

        $this->assertSame(EtapaSelecao::Aprovado, $this->etapa());
        $this->assertSame(EtapaSelecao::Entrevista, $this->etapa($outra));
    }

    public function test_vaga_encerrada_rejeita_a_movimentacao(): void
    {
        $encerrada = $this->vaga(['status' => 'encerrada', 'encerrada_em' => now()]);

        $this->mover('entrevista', vaga: $encerrada)
            ->assertSessionHasErrors(['etapa' => 'Vaga encerrada — somente consulta. Não é possível mover candidatos.']);

        $this->assertSame(0, Participacao::query()->count());
    }

    public function test_candidato_desativado_ou_inelegivel_nao_pode_ser_movido(): void
    {
        $desativado = $this->candidato(['status' => 'desativado']);
        $servidor = Candidato::factory()->servidor()->create();

        $this->mover('entrevista', $desativado)
            ->assertSessionHasErrors(['etapa' => 'Este candidato está desativado e não pode ser movido.']);
        $this->mover('entrevista', $servidor)
            ->assertSessionHasErrors(['etapa' => 'Este candidato não é elegível para esta vaga.']);

        $this->assertSame(0, Participacao::query()->count());
    }

    #[TestWith(['contratado'])]
    #[TestWith([''])]
    public function test_etapa_invalida_e_rejeitada(string $etapa): void
    {
        $this->mover($etapa)->assertSessionHasErrors('etapa');

        $this->assertSame(0, Participacao::query()->count());
    }

    public function test_quadro_separa_as_tres_colunas_com_contagens_e_acoes(): void
    {
        $emEntrevista = $this->candidato(['nome' => 'Joana Prates']);
        $aprovado = $this->candidato(['nome' => 'Caio Teles']);
        $this->mover('entrevista', $emEntrevista);
        $this->mover('aprovado', $aprovado);

        $html = $this->get(route('vagas.show', $this->vaga))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-etapa="triagem".*'.$this->candidato->codigo.'.*data-etapa="entrevista".*Joana Prates.*data-etapa="aprovado".*Caio Teles/s', $html);
        $this->assertStringContainsString('Mover para Entrevista', $html);
        $this->assertStringContainsString('Aprovar', $html);
        $this->assertStringContainsString('Voltar para Triagem', $html);
        $this->assertStringContainsString('Voltar para Entrevista', $html);
        $this->assertStringContainsString('draggable="true"', $html);
        $this->assertStringContainsString(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]), $html);
        $this->assertStringContainsString('3 candidatos elegíveis', $html);
    }

    public function test_desativado_com_participacao_continua_visivel_esmaecido_e_sem_acoes(): void
    {
        $this->mover('entrevista');
        $this->candidato->update(['status' => 'desativado']);

        $html = $this->get(route('vagas.show', $this->vaga))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/data-etapa="entrevista".*'.preg_quote($this->candidato->nome, '/').'.*Desativado/s', $html);
        $this->assertStringNotContainsString('Aprovar', $html);
        $this->assertStringNotContainsString('draggable="true"', $html);

        $this->mover('aprovado')->assertSessionHasErrors('etapa');
    }

    public function test_vaga_encerrada_nao_mostra_acoes_de_movimentacao(): void
    {
        $this->mover('entrevista');
        $this->vaga->update(['status' => 'encerrada', 'encerrada_em' => now()]);

        $this->get(route('vagas.show', $this->vaga))
            ->assertSee('Vaga encerrada — somente consulta')
            ->assertSee($this->candidato->nome)
            ->assertDontSee('Aprovar')
            ->assertDontSee('Mover para Entrevista');
        $this->get(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]))
            ->assertSee('Vaga encerrada — somente consulta')
            ->assertDontSee('Aprovar');
    }

    public function test_perfil_mostra_o_historico_da_vaga_com_autor_e_data(): void
    {
        $this->mover('entrevista');
        $this->mover('aprovado', extra: ['contexto' => 'perfil'])
            ->assertRedirect(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]));

        $movimentacoes = MovimentacaoEtapa::query()->orderBy('id')->get();

        $this->get(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]))
            ->assertOk()
            ->assertSee('Movido para Entrevista por Recrutadora Teste em '.$movimentacoes[0]->created_at->format('d/m/Y H:i'))
            ->assertSee('Movido para Aprovado por Recrutadora Teste em '.$movimentacoes[1]->created_at->format('d/m/Y H:i'))
            ->assertSee('Voltar para Entrevista');
    }

    public function test_botao_ajax_persiste_uma_movimentacao_e_devolve_o_quadro(): void
    {
        $resposta = $this->mover('entrevista', extra: ['contexto' => 'quadro'], json: true)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('message', $this->candidato->nome.' está agora em Entrevista.')
            ->assertJsonPath('redirect', null);

        $this->assertSame(['quadro-vaga'], array_keys($resposta->json('fragments')));
        $this->assertMatchesRegularExpression(
            '/data-etapa="entrevista".*'.preg_quote($this->candidato->nome, '/').'/s',
            $resposta->json('fragments.quadro-vaga'),
        );
        $this->assertSame(1, MovimentacaoEtapa::query()->count());
    }

    public function test_sem_contexto_o_json_devolve_o_quadro_e_com_perfil_devolve_o_perfil(): void
    {
        $this->mover('entrevista', json: true)->assertOk()->assertJsonStructure(['fragments' => ['quadro-vaga']]);

        $perfil = $this->mover('aprovado', extra: ['contexto' => 'perfil'], json: true)->assertOk();

        $this->assertSame(['perfil-na-vaga'], array_keys($perfil->json('fragments')));
        $this->assertStringContainsString('Movido para Aprovado por Recrutadora Teste', $perfil->json('fragments.perfil-na-vaga'));
    }

    #[TestWith([['etapa' => 'contratado'], 'etapa'])]
    #[TestWith([['etapa' => 'entrevista', 'contexto' => 'candidatos.show'], 'contexto'])]
    #[TestWith([['etapa' => 'entrevista', 'contexto' => '#conteudo'], 'contexto'])]
    public function test_json_invalido_retorna_422_sem_movimentar(array $dados, string $campo): void
    {
        $this->patchJson(route('vagas.candidatos.etapa', [$this->vaga, $this->candidato->codigo]), $dados)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($campo)
            ->assertJsonMissingPath('fragments');

        $this->assertSame(0, MovimentacaoEtapa::query()->count());
    }

    public function test_json_recusa_vaga_encerrada_desativado_e_inelegivel_com_422(): void
    {
        $encerrada = $this->vaga(['status' => 'encerrada', 'encerrada_em' => now()]);
        $desativado = $this->candidato(['status' => 'desativado', 'nome' => 'Nome Oculto Desativado']);
        $servidor = Candidato::factory()->servidor()->create(['nome' => 'Nome Oculto Servidor']);

        $this->mover('entrevista', vaga: $encerrada, json: true)->assertUnprocessable()->assertJsonValidationErrors('etapa');
        $this->mover('entrevista', $desativado, json: true)->assertUnprocessable()
            ->assertJsonValidationErrors('etapa')->assertDontSee('Nome Oculto Desativado');
        $this->mover('entrevista', $servidor, json: true)->assertUnprocessable()
            ->assertJsonValidationErrors('etapa')->assertDontSee('Nome Oculto Servidor');

        $this->assertSame(0, MovimentacaoEtapa::query()->count());
    }

    public function test_visitante_nao_movimenta(): void
    {
        $this->app['auth']->forgetGuards();

        $this->mover('entrevista')->assertRedirect(route('login'));
        $this->mover('entrevista', json: true)->assertUnauthorized();

        $this->assertSame(0, Participacao::query()->count());
    }

    public function test_seeder_deixa_dois_servidores_em_entrevista_na_vaga_de_dados(): void
    {
        Storage::fake('curriculos');
        $this->seed([UsuarioSeeder::class, CompetenciaSeeder::class, CandidatoSeeder::class, VagaSeeder::class, VagaSeeder::class]);

        $dados = Vaga::query()->where('titulo', 'Analista de Dados')->sole();
        $recrutadora = User::query()->where('email', 'recrutador@setic.demo')->sole();
        $participacoes = Participacao::query()->where('vaga_id', $dados->id)->with('candidato')->get();

        $this->assertEqualsCanonicalizing(['Bruno Peixoto', 'Renata Barros'], $participacoes->pluck('candidato.nome')->all());
        $this->assertTrue($participacoes->every(fn (Participacao $participacao): bool => $participacao->etapa === EtapaSelecao::Entrevista));
        $this->assertSame(2, MovimentacaoEtapa::query()->where('user_id', $recrutadora->id)->count());

        $this->get(route('vagas.show', $dados))->assertSee('Bruno Peixoto')->assertSee('Renata Barros');
    }
}
