<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use App\Services\Curriculo\CadastroCandidatoService;
use Database\Seeders\CompetenciaSeeder;
use Database\Seeders\Support\GeradorPdfCurriculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class ImportacaoLoteTest extends TestCase
{
    use RefreshDatabase;

    private const MENSAGEM_CONSENTIMENTO = 'Registre como e quando o candidato consentiu com o uso dos dados.';

    private User $recrutador;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('curriculos');
        $this->seed(CompetenciaSeeder::class);
        $this->recrutador = User::factory()->create();
        $this->actingAs($this->recrutador);
    }

    private function pdf(string $nome, string $email): UploadedFile
    {
        return UploadedFile::fake()->createWithContent("{$nome}.pdf", (new GeradorPdfCurriculo)->gerar([
            str_replace('-', ' ', Str::title($nome)),
            "{$email} | (69) 99876-5432",
            'FORMAÇÃO',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício)',
            'EXPERIÊNCIA PROFISSIONAL',
            'Estágio no Laboratório Horizonte (fictício) com Python e SQL',
            'COMPETÊNCIAS',
            'Python, SQL, Git',
        ]));
    }

    /** @return list<UploadedFile> */
    private function tresPdfs(): array
    {
        return [
            $this->pdf('ana-lima', 'ana.lima@exemplo.com.br'),
            $this->pdf('bruno-teixeira', 'bruno.teixeira@exemplo.com.br'),
            $this->pdf('carla-menezes', 'carla.menezes@exemplo.com.br'),
        ];
    }

    /** @param list<UploadedFile> $arquivos */
    private function enviarLote(array $arquivos): string
    {
        $resposta = $this->post(route('importacoes.store'), ['arquivos' => $arquivos, 'vaga_id' => Vaga::factory()->create()->id]);
        $resposta->assertRedirect();
        $lote = Str::afterLast(parse_url($resposta->headers->get('Location'), PHP_URL_PATH), '/');
        $this->assertSame(route('importacoes.lotes.show', $lote), $resposta->headers->get('Location'));

        return $lote;
    }

    /** @return array{consentimento_meio: string, consentimento_em: string} */
    private function consentimento(): array
    {
        return ['consentimento_meio' => 'email', 'consentimento_em' => now()->toDateString()];
    }

    /** @return list<array<string, mixed>> */
    private function itens(string $lote): array
    {
        return session("importacoes_lotes.{$lote}");
    }

    private function dadosRevisados(string $email): array
    {
        return [
            'nome' => 'Pessoa Revisada',
            'email' => $email,
            'telefone' => '',
            'cpf' => '',
            'data_nascimento' => '',
            'tipo' => 'estagiario',
            'area' => 'desenvolvimento',
            'experiencia_na_area' => '1',
            'formacoes' => 'Análise e Desenvolvimento de Sistemas',
            'experiencias' => '',
            'projetos' => '',
            'links' => '',
            'competencias' => [],
            ...$this->consentimento(),
            'modo' => 'novo',
        ];
    }

    public function test_um_unico_pdf_mantem_o_fluxo_direto_para_a_revisao(): void
    {
        $resposta = $this->post(route('importacoes.store'), ['arquivos' => [$this->pdf('ana-lima', 'ana@exemplo.com.br')], 'vaga_id' => Vaga::factory()->create()->id]);

        $uuid = Str::afterLast($resposta->headers->get('Location'), '/');
        $resposta->assertRedirect(route('importacoes.show', $uuid));
        $this->assertNull(session('abrir_escolha_lote'));

        $this->get(route('importacoes.show', $uuid))
            ->assertOk()
            ->assertSee('Enviar outro currículo')
            ->assertDontSee('Voltar ao lote')
            ->assertSee('Confirmar e salvar');
    }

    public function test_varios_pdfs_abrem_a_modal_com_as_duas_opcoes(): void
    {
        $lote = $this->enviarLote($this->tresPdfs());

        $pagina = $this->get(route('importacoes.lotes.show', $lote));

        $pagina->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('data-abrir-ao-carregar', false)
            ->assertSee('3 currículos lidos')
            ->assertSee('Vários currículos foram importados')
            ->assertSee('Revisar um por um')
            ->assertSee('Cadastrar sem revisão')
            ->assertSee('3 prontos para cadastro direto')
            ->assertSee('ana-lima.pdf');

        $this->assertSame(0, Candidato::query()->count());
        $this->assertCount(3, Storage::disk('curriculos')->allFiles('tmp'));

        $this->get(route('importacoes.lotes.show', $lote))->assertDontSee('data-abrir-ao-carregar', false);
    }

    public function test_lote_json_devolve_redirect_para_o_resumo(): void
    {
        $resposta = $this->post(route('importacoes.store'), ['arquivos' => $this->tresPdfs(), 'vaga_id' => Vaga::factory()->create()->id], ['Accept' => 'application/json']);

        $resposta->assertOk()->assertJsonPath('fragments', [])->assertJsonPath('message', 'Currículos lidos. Escolha como continuar.');
        $this->assertStringContainsString('/candidatos/importar/lotes/', $resposta->json('redirect'));
    }

    public function test_recusa_mais_arquivos_que_o_limite_sem_guardar_nada(): void
    {
        $arquivos = array_map(fn (int $numero): UploadedFile => $this->pdf("pessoa-{$numero}", "p{$numero}@exemplo.com.br"), range(1, 11));

        $this->post(route('importacoes.store'), ['arquivos' => $arquivos, 'tipo' => 'estagiario'])
            ->assertSessionHasErrors(['arquivos' => 'Envie no máximo 10 currículos por vez.']);

        $this->assertSame([], Storage::disk('curriculos')->allFiles());
    }

    public function test_arquivo_invalido_recusa_o_lote_indicando_qual_corrigir(): void
    {
        $this->post(route('importacoes.store'), [
            'arquivos' => [
                $this->pdf('ana-lima', 'ana@exemplo.com.br'),
                UploadedFile::fake()->createWithContent('foto.txt', 'não é pdf'),
            ],
            'tipo' => 'estagiario',
        ])->assertSessionHasErrors(['arquivos.1' => 'foto.txt: envie um arquivo PDF de até 10 MB.']);

        $this->assertSame([], Storage::disk('curriculos')->allFiles());
    }

    public function test_envio_sem_arquivos_e_recusado(): void
    {
        $this->post(route('importacoes.store'), ['tipo' => 'estagiario'])
            ->assertSessionHasErrors(['arquivos' => 'Selecione ao menos um currículo em PDF.']);
    }

    public function test_revisao_um_por_um_percorre_a_fila_e_termina_no_resumo(): void
    {
        $lote = $this->enviarLote($this->tresPdfs());
        [$primeiro, $segundo, $terceiro] = array_column($this->itens($lote), 'uuid');

        $this->get(route('importacoes.show', $primeiro))
            ->assertOk()
            ->assertSee('Currículo 1 de 3')
            ->assertSee('Voltar ao lote')
            ->assertSee('Pular para o próximo')
            ->assertSee('Salvar e seguir para o próximo');

        $this->post(route('importacoes.confirmar', $primeiro), $this->dadosRevisados('um@exemplo.com.br'))
            ->assertRedirect(route('importacoes.show', $segundo))
            ->assertSessionHas('status', 'Candidato salvo no banco de talentos.');

        $this->get(route('importacoes.show', $segundo))->assertSee('Currículo 2 de 3');

        $this->post(route('importacoes.confirmar', $segundo), $this->dadosRevisados('dois@exemplo.com.br'))
            ->assertRedirect(route('importacoes.show', $terceiro));

        $this->get(route('importacoes.show', $terceiro))
            ->assertSee('Currículo 3 de 3')
            ->assertDontSee('Pular para o próximo')
            ->assertSee('Confirmar e salvar');

        $this->post(route('importacoes.confirmar', $terceiro), $this->dadosRevisados('tres@exemplo.com.br'))
            ->assertRedirect(route('importacoes.lotes.show', $lote));

        $this->assertSame(3, Candidato::query()->count());
        $this->get(route('importacoes.lotes.show', $lote))
            ->assertOk()
            ->assertSee('3 de 3 cadastrados')
            ->assertSee('Ver perfil')
            ->assertSee('Ver banco de talentos')
            ->assertDontSee('Cadastrar sem revisão');
    }

    public function test_pular_volta_ao_curriculo_pulado_ao_fim_da_fila(): void
    {
        $lote = $this->enviarLote(array_slice($this->tresPdfs(), 0, 2));
        [$primeiro, $segundo] = array_column($this->itens($lote), 'uuid');

        $this->get(route('importacoes.show', $primeiro))->assertSee(route('importacoes.show', $segundo), false);

        $this->post(route('importacoes.confirmar', $segundo), $this->dadosRevisados('dois@exemplo.com.br'))
            ->assertRedirect(route('importacoes.show', $primeiro));
    }

    public function test_cadastro_sem_revisao_exige_consentimento_e_nao_salva_nada_sem_ele(): void
    {
        $lote = $this->enviarLote($this->tresPdfs());

        $this->from(route('importacoes.lotes.show', $lote))
            ->post(route('importacoes.lotes.cadastrar', $lote), [])
            ->assertRedirect(route('importacoes.lotes.show', $lote))
            ->assertSessionHasErrors([
                'consentimento_meio' => self::MENSAGEM_CONSENTIMENTO,
                'consentimento_em' => self::MENSAGEM_CONSENTIMENTO,
            ]);

        $this->assertSame(0, Candidato::query()->count());
        $this->get(route('importacoes.lotes.show', $lote))->assertSee('data-abrir-ao-carregar', false);

        $this->post(route('importacoes.lotes.cadastrar', $lote), ['consentimento_meio' => 'email', 'consentimento_em' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('consentimento_em');
        $this->post(route('importacoes.lotes.cadastrar', $lote), ['consentimento_meio' => 'telepatia', 'consentimento_em' => now()->toDateString()])
            ->assertSessionHasErrors('consentimento_meio');

        $this->assertSame(0, Candidato::query()->count());
    }

    public function test_cadastro_sem_revisao_salva_os_dados_lidos_com_consentimento_e_autor(): void
    {
        $lote = $this->enviarLote($this->tresPdfs());

        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento())
            ->assertRedirect(route('importacoes.lotes.show', $lote))
            ->assertSessionHas('status', '3 candidatos cadastrados.');

        $this->assertSame(3, Candidato::query()->count());
        Storage::disk('curriculos')->assertDirectoryEmpty('tmp');

        $candidato = Candidato::query()->where('email', 'ana.lima@exemplo.com.br')->sole();
        $this->assertSame('Ana Lima', $candidato->nome);
        $this->assertNull($candidato->tipo);
        $this->assertSame('email', $candidato->consentimento_meio->value);
        $this->assertTrue($candidato->consentimento_em->isToday());
        $this->assertSame($this->recrutador->id, $candidato->consentimento_registrado_por);
        $this->assertSame('ana-lima.pdf', $candidato->curriculo_nome_original);
        Storage::disk('curriculos')->assertExists($candidato->curriculo_arquivo);
        $this->assertSame(
            ['curriculo'],
            $candidato->competencias->pluck('pivot.origem')->unique()->values()->all(),
        );
        $this->assertContains('Python', $candidato->competencias->pluck('nome')->all());

        $this->get(route('importacoes.lotes.show', $lote))
            ->assertOk()
            ->assertSee('Cadastrado')
            ->assertSee('Ana Lima')
            ->assertSee(route('candidatos.show', $candidato), false);
    }

    public function test_cadastro_sem_revisao_deixa_pendente_o_que_nao_e_seguro_salvar(): void
    {
        $existente = Candidato::factory()->create(['nome' => 'Cadastro Antigo', 'email' => 'duplicada@exemplo.com.br']);
        $semArea = UploadedFile::fake()->createWithContent('sem-area.pdf', (new GeradorPdfCurriculo)->gerar([
            'Diego Prado',
            'diego.prado@exemplo.com.br',
            'Resumo sem nenhuma competência do catálogo, apenas texto corrido suficiente para ser lido.',
        ]));
        $semNome = UploadedFile::fake()->createWithContent('sem-nome.pdf', (new GeradorPdfCurriculo)->gerar([
            'FORMAÇÃO',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício)',
            'COMPETÊNCIAS',
            'Python, SQL, Git, Docker, Linux',
        ]));

        $lote = $this->enviarLote([
            $this->pdf('ana-lima', 'ana.lima@exemplo.com.br'),
            $this->pdf('pessoa-duplicada', 'duplicada@exemplo.com.br'),
            UploadedFile::fake()->createWithContent('escaneado.pdf', (new GeradorPdfCurriculo)->gerarSemTexto()),
            UploadedFile::fake()->createWithContent('corrompido.pdf', "%PDF-1.4\nconteúdo truncado sem objetos"),
            $semArea,
            $semNome,
        ]);

        $this->get(route('importacoes.lotes.show', $lote))
            ->assertSee('1 pronto para cadastro direto')
            ->assertSee('5 precisam de revisão manual')
            ->assertSee('Cadastrar 1 sem revisão')
            ->assertSee('Possível cadastro duplicado: Cadastro Antigo.')
            ->assertSee('parece digitalizado')
            ->assertSee('O arquivo parece corrompido.')
            ->assertSee('Área de atuação não identificada.')
            ->assertSee('Nome não identificado.');

        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento())
            ->assertSessionHas('status', '1 candidato cadastrado. 5 currículos precisam de revisão manual.');

        $this->assertSame(2, Candidato::query()->count());
        $this->assertSame('Cadastro Antigo', $existente->refresh()->nome);
        $this->assertCount(5, Storage::disk('curriculos')->allFiles('tmp'));
    }

    public function test_dois_curriculos_do_lote_com_o_mesmo_email_nao_viram_dois_cadastros(): void
    {
        $lote = $this->enviarLote([
            $this->pdf('ana-lima', 'mesmo@exemplo.com.br'),
            $this->pdf('ana-lima-silva', 'mesmo@exemplo.com.br'),
        ]);

        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento())
            ->assertSessionHas('status', '1 candidato cadastrado. 1 currículo precisa de revisão manual.');

        $this->assertSame(1, Candidato::query()->count());
        $this->get(route('importacoes.lotes.show', $lote))->assertSee('Possível cadastro duplicado: Ana Lima.');
    }

    public function test_falha_ao_salvar_um_curriculo_nao_interrompe_os_demais(): void
    {
        $lote = $this->enviarLote($this->tresPdfs());
        $real = $this->app->make(CadastroCandidatoService::class);
        $chamadas = 0;

        $this->mock(CadastroCandidatoService::class)
            ->shouldReceive('confirmar')
            ->andReturnUsing(function (array $importacao, array $revisados, User $recrutador) use ($real, &$chamadas): Candidato {
                if (++$chamadas === 1) {
                    throw new RuntimeException('Disco indisponível (simulado).');
                }

                return $real->confirmar($importacao, $revisados, $recrutador);
            });

        $this->withoutExceptionHandling();
        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento())
            ->assertSessionHas('status', '2 candidatos cadastrados. 1 currículo precisa de revisão manual.');

        $this->assertSame(2, Candidato::query()->count());
    }

    public function test_cadastro_sem_revisao_nao_repete_quem_ja_foi_salvo(): void
    {
        $lote = $this->enviarLote($this->tresPdfs());

        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento());
        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento())
            ->assertSessionHas('status', 'Nenhum candidato cadastrado.');

        $this->assertSame(3, Candidato::query()->count());
    }

    public function test_descartar_apaga_o_pdf_temporario_e_tira_o_curriculo_da_fila(): void
    {
        $lote = $this->enviarLote(array_slice($this->tresPdfs(), 0, 2));
        [$primeiro, $segundo] = array_column($this->itens($lote), 'uuid');

        $this->delete(route('importacoes.destroy', $primeiro))->assertRedirect(route('importacoes.lotes.show', $lote));

        Storage::disk('curriculos')->assertMissing("tmp/{$primeiro}.pdf");
        Storage::disk('curriculos')->assertExists("tmp/{$segundo}.pdf");
        $this->get(route('importacoes.show', $primeiro))->assertNotFound();
        $this->get(route('importacoes.lotes.show', $lote))->assertSee('Descartado');
        $this->assertSame(0, Candidato::query()->count());

        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento())
            ->assertSessionHas('status', '1 candidato cadastrado.');
    }

    public function test_descartar_fora_de_lote_volta_para_a_tela_de_envio(): void
    {
        $resposta = $this->post(route('importacoes.store'), ['arquivos' => [$this->pdf('ana-lima', 'ana@exemplo.com.br')], 'vaga_id' => Vaga::factory()->create()->id]);
        $uuid = Str::afterLast($resposta->headers->get('Location'), '/');

        $this->delete(route('importacoes.destroy', $uuid))->assertRedirect(route('importacoes.create'));

        Storage::disk('curriculos')->assertMissing("tmp/{$uuid}.pdf");
    }

    public function test_lote_de_outra_sessao_ou_expirado_nao_e_acessivel(): void
    {
        $lote = $this->enviarLote($this->tresPdfs());

        $this->flushSession();

        $this->get(route('importacoes.lotes.show', $lote))->assertNotFound()->assertSee('Enviar novamente');
        $this->post(route('importacoes.lotes.cadastrar', $lote), $this->consentimento())->assertNotFound();
        $this->getJson(route('importacoes.lotes.show', $lote))->assertNotFound();
        $this->assertSame(0, Candidato::query()->count());
    }

    public function test_visitante_nao_acessa_as_rotas_do_lote(): void
    {
        $uuid = (string) Str::uuid();
        $this->app['auth']->forgetGuards();

        $this->get(route('importacoes.lotes.show', $uuid))->assertRedirect(route('login'));
        $this->post(route('importacoes.lotes.cadastrar', $uuid), $this->consentimento())->assertRedirect(route('login'));
        $this->delete(route('importacoes.destroy', $uuid))->assertRedirect(route('login'));
    }

    public function test_rotas_do_lote_aceitam_somente_uuid(): void
    {
        $this->get('/candidatos/importar/lotes/nao-e-uuid')->assertNotFound();
        $this->post('/candidatos/importar/lotes/nao-e-uuid/cadastrar', $this->consentimento())->assertNotFound();
    }
}
