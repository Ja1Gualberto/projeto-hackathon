<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\Participacao;
use App\Models\User;
use App\Models\Vaga;
use Database\Seeders\CompetenciaSeeder;
use Database\Seeders\Support\GeradorPdfCurriculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_busca_para_triagem_combina_competencias_e_exclui_inativos_e_ja_vinculados(): void
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
        Participacao::query()->create(['vaga_id' => $vaga->id, 'candidato_id' => $vinculado->id, 'etapa' => 'triagem']);

        $this->actingAs(User::factory()->create())->get(route('vagas.triagem.index', [$vaga, 'competencias' => [$python->id, $sql->id]]))
            ->assertOk()->assertSee($selecionavel->codigo)->assertDontSee($selecionavel->nome)
            ->assertDontSee($vinculado->codigo)->assertDontSee($inativo->codigo)->assertDontSee($parcial->codigo);
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
        $this->getJson(route('vagas.show', $vaga))->assertOk()->assertJsonPath('data.quadro-vaga.vaga.selecionarUrl', null);

        $this->assertDatabaseCount('participacoes', 0);
    }

    public function test_rotas_de_selecao_exigem_autenticacao(): void
    {
        $vaga = Vaga::factory()->create();

        $this->get(route('vagas.triagem.index', $vaga))->assertRedirect(route('login'));
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
