<?php

namespace Tests\Feature;

use App\Enums\OrigemCompetencia;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use Database\Seeders\CompetenciaSeeder;
use Database\Seeders\Support\GeradorPdfCurriculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ImportacaoCurriculoTest extends TestCase
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

    /** @param list<string>|null $linhas */
    private function pdf(?array $linhas = null, string $nome = 'curriculo-ana.pdf'): UploadedFile
    {
        $linhas ??= [
            'Ana Lima',
            'ana.lima@exemplo.com.br | (69) 99876-5432',
            'FORMAÇÃO',
            'Análise e Desenvolvimento de Sistemas — Instituto Vale Digital (fictício)',
            'EXPERIÊNCIA PROFISSIONAL',
            'Estágio no Laboratório Horizonte (fictício) com Python e SQL',
            'PROJETOS',
            'Painel de chamados em Python',
            'https://github.com/ana-lima-demo',
            'COMPETÊNCIAS',
            'Python, SQL, Git, Kubernetes',
            'Trabalho em equipe, Comunicação',
        ];

        return UploadedFile::fake()->createWithContent($nome, (new GeradorPdfCurriculo)->gerar($linhas));
    }

    private function importar(?UploadedFile $arquivo = null, string $tipo = 'estagiario'): string
    {
        $resposta = $this->post(route('importacoes.store'), ['arquivo' => $arquivo ?? $this->pdf(), 'tipo' => $tipo]);
        $resposta->assertRedirect();
        $uuid = Str::afterLast(parse_url($resposta->headers->get('Location'), PHP_URL_PATH), '/');
        $this->assertTrue(Str::isUuid($uuid));

        return $uuid;
    }

    private function idCompetencia(string $nome): int
    {
        return Competencia::query()->where('nome', $nome)->value('id');
    }

    /** @return array<string, mixed> */
    private function dadosConfirmacao(array $sobrescrever = []): array
    {
        return [
            'nome' => 'Ana Lima',
            'email' => 'ana.lima@exemplo.com.br',
            'telefone' => '(69) 99876-5432',
            'cpf' => '',
            'data_nascimento' => '',
            'tipo' => 'estagiario',
            'area' => 'desenvolvimento',
            'experiencia_na_area' => '1',
            'formacoes' => "Análise e Desenvolvimento de Sistemas\n\n",
            'experiencias' => 'Estágio no Laboratório Horizonte (fictício) com Python e SQL',
            'projetos' => 'Painel de chamados em Python',
            'links' => "https://github.com/ana-lima-demo\n",
            'competencias' => [$this->idCompetencia('Python'), $this->idCompetencia('Docker')],
            'consentimento_meio' => 'email',
            'consentimento_em' => now()->toDateString(),
            'modo' => 'novo',
            ...$sobrescrever,
        ];
    }

    private function confirmar(string $uuid, array $dados, bool $json = false): TestResponse
    {
        return $this->post(route('importacoes.confirmar', $uuid), $dados, $json ? ['Accept' => 'application/json'] : []);
    }

    public function test_tela_de_envio_pede_pdf_e_tipo(): void
    {
        $this->get(route('importacoes.create'))
            ->assertOk()
            ->assertSee('Ler currículo')
            ->assertSee('PDF com texto selecionável, até 10 MB')
            ->assertSee('Servidor')
            ->assertSee('Estagiário');
    }

    public function test_upload_valido_redireciona_para_revisao_pre_preenchida(): void
    {
        $uuid = $this->importar();

        Storage::disk('curriculos')->assertExists("tmp/{$uuid}.pdf");

        $this->get(route('importacoes.show', $uuid))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('value="Ana Lima"', false)
            ->assertSee('value="ana.lima@exemplo.com.br"', false)
            ->assertSee('Análise e Desenvolvimento de Sistemas')
            ->assertSee('value="'.$this->idCompetencia('Python').'" checked', false)
            ->assertSee('declarada no currículo')
            ->assertSee('Kubernetes')
            ->assertSee('Adicionar ao catálogo')
            ->assertSee('Confirmar e salvar');
    }

    public function test_arquivo_que_nao_e_pdf_e_rejeitado(): void
    {
        $this->from(route('importacoes.create'))
            ->post(route('importacoes.store'), [
                'arquivo' => UploadedFile::fake()->createWithContent('curriculo.txt', 'Ana Lima, Python e SQL'),
                'tipo' => 'estagiario',
            ])
            ->assertRedirect(route('importacoes.create'))
            ->assertSessionHasErrors(['arquivo' => 'Envie um arquivo PDF de até 10 MB.']);

        $this->assertSame([], Storage::disk('curriculos')->allFiles());
    }

    public function test_pdf_acima_de_10_mb_e_rejeitado(): void
    {
        $grande = UploadedFile::fake()->createWithContent(
            'grande.pdf',
            (new GeradorPdfCurriculo)->gerar(['Ana Lima']).str_repeat(' ', 10241 * 1024),
        );

        $this->post(route('importacoes.store'), ['arquivo' => $grande, 'tipo' => 'servidor'])
            ->assertSessionHasErrors(['arquivo' => 'Envie um arquivo PDF de até 10 MB.']);
    }

    public function test_tipo_e_obrigatorio(): void
    {
        $this->post(route('importacoes.store'), ['arquivo' => $this->pdf()])
            ->assertSessionHasErrors('tipo');
    }

    public function test_pdf_sem_texto_mantem_o_arquivo_e_oferece_preenchimento_manual(): void
    {
        $semTexto = UploadedFile::fake()->createWithContent('escaneado.pdf', (new GeradorPdfCurriculo)->gerarSemTexto());
        $uuid = $this->importar($semTexto, 'servidor');

        Storage::disk('curriculos')->assertExists("tmp/{$uuid}.pdf");

        $this->get(route('importacoes.show', $uuid))
            ->assertOk()
            ->assertSee('Não conseguimos ler o texto deste PDF (parece digitalizado). Preencha os dados manualmente.')
            ->assertSee('Confirmar e salvar');
    }

    public function test_pdf_corrompido_mostra_aviso_e_preenchimento_manual(): void
    {
        $corrompido = UploadedFile::fake()->createWithContent('corrompido.pdf', "%PDF-1.4\nconteúdo truncado sem objetos");
        $uuid = $this->importar($corrompido);

        $this->get(route('importacoes.show', $uuid))
            ->assertOk()
            ->assertSee('O arquivo parece corrompido.');
    }

    public function test_confirmar_sem_consentimento_nao_cria_candidato(): void
    {
        $uuid = $this->importar();

        $this->from(route('importacoes.show', $uuid))
            ->confirmar($uuid, $this->dadosConfirmacao(['consentimento_meio' => '', 'consentimento_em' => '']))
            ->assertRedirect(route('importacoes.show', $uuid))
            ->assertSessionHasErrors([
                'consentimento_meio' => self::MENSAGEM_CONSENTIMENTO,
                'consentimento_em' => self::MENSAGEM_CONSENTIMENTO,
            ]);

        $this->assertSame(0, Candidato::query()->count());
    }

    public function test_confirmacao_valida_cria_candidato_com_pdf_definitivo_e_origens(): void
    {
        $uuid = $this->importar();

        $resposta = $this->confirmar($uuid, $this->dadosConfirmacao());

        $candidato = Candidato::query()->sole();
        $resposta->assertRedirect(route('candidatos.show', $candidato))
            ->assertSessionHas('status', 'Candidato salvo no banco de talentos.');

        $this->assertMatchesRegularExpression('/^CAND-\d{4}$/', $candidato->codigo);
        $this->assertStringStartsNotWith('tmp/', $candidato->curriculo_arquivo);
        Storage::disk('curriculos')->assertExists($candidato->curriculo_arquivo);
        Storage::disk('curriculos')->assertMissing("tmp/{$uuid}.pdf");
        $this->assertSame('curriculo-ana.pdf', $candidato->curriculo_nome_original);
        $this->assertStringContainsString('Python', $candidato->texto_curriculo);
        $this->assertSame(['Análise e Desenvolvimento de Sistemas'], $candidato->formacoes);
        $this->assertSame(['https://github.com/ana-lima-demo'], $candidato->links);
        $this->assertTrue($candidato->experiencia_na_area);
        $this->assertSame($this->recrutador->id, $candidato->consentimento_registrado_por);
        $this->assertNotNull($candidato->dados_atualizados_em);

        $origens = $candidato->competencias->mapWithKeys(fn (Competencia $item): array => [$item->nome => $item->pivot->origem]);
        $this->assertSame(['Docker' => 'revisao', 'Python' => 'curriculo'], $origens->sortKeys()->all());

        $this->get(route('importacoes.show', $uuid))->assertNotFound();
        $this->get(route('candidatos.index', ['busca' => 'Ana Lima']))->assertOk()->assertSee('Ana Lima');
    }

    public function test_duplicidade_por_email_mostra_alerta_com_o_cadastro_existente(): void
    {
        $existente = Candidato::factory()->create(['nome' => 'Ana Lima Antiga', 'email' => 'ana.lima@exemplo.com.br']);
        $uuid = $this->importar();

        $this->get(route('importacoes.show', $uuid))
            ->assertOk()
            ->assertSee('Possível cadastro duplicado')
            ->assertSee('Ana Lima Antiga')
            ->assertSee('Atualizar cadastro existente')
            ->assertSee('Criar novo mesmo assim')
            ->assertSee('name="candidato_existente_id" value="'.$existente->id.'"', false);
    }

    public function test_modo_atualizar_atualiza_o_existente_sem_rebaixar_origem_de_entrevista(): void
    {
        $existente = Candidato::factory()->create([
            'nome' => 'Ana Lima Antiga',
            'email' => 'ana.lima@exemplo.com.br',
            'curriculo_arquivo' => 'antigo.pdf',
            'dados_atualizados_em' => now()->subYear(),
        ]);
        Storage::disk('curriculos')->put('antigo.pdf', '%PDF-1.4 antigo');
        $existente->competencias()->attach([
            $this->idCompetencia('Comunicação') => ['origem' => OrigemCompetencia::Entrevista->value],
            $this->idCompetencia('Linux') => ['origem' => OrigemCompetencia::Curriculo->value],
        ]);
        $codigo = $existente->codigo;
        $uuid = $this->importar();

        $this->confirmar($uuid, $this->dadosConfirmacao([
            'modo' => 'atualizar',
            'candidato_existente_id' => $existente->id,
            'competencias' => [$this->idCompetencia('Python'), $this->idCompetencia('Comunicação')],
        ]))->assertRedirect(route('candidatos.show', $existente));

        $this->assertSame(1, Candidato::query()->count());
        $existente->refresh();
        $this->assertSame('Ana Lima', $existente->nome);
        $this->assertSame($codigo, $existente->codigo);
        $this->assertTrue($existente->dados_atualizados_em->isToday());
        Storage::disk('curriculos')->assertMissing('antigo.pdf');
        Storage::disk('curriculos')->assertExists($existente->curriculo_arquivo);

        $origens = $existente->competencias->mapWithKeys(fn (Competencia $item): array => [$item->nome => $item->pivot->origem]);
        $this->assertSame('entrevista', $origens['Comunicação']);
        $this->assertSame('curriculo', $origens['Python']);
        $this->assertSame('curriculo', $origens['Linux']);
    }

    public function test_modo_atualizar_recusa_cadastro_que_nao_corresponde_ao_curriculo(): void
    {
        $outro = Candidato::factory()->create(['email' => 'outra.pessoa@exemplo.com.br']);
        $uuid = $this->importar();

        $this->confirmar($uuid, $this->dadosConfirmacao(['modo' => 'atualizar', 'candidato_existente_id' => $outro->id]))
            ->assertSessionHasErrors('candidato_existente_id');

        $this->assertSame(1, Candidato::query()->count());
    }

    public function test_criar_novo_mesmo_com_duplicidade_e_permitido(): void
    {
        Candidato::factory()->create(['email' => 'ana.lima@exemplo.com.br']);
        $uuid = $this->importar();

        $this->confirmar($uuid, $this->dadosConfirmacao())->assertRedirect();

        $this->assertSame(2, Candidato::query()->count());
    }

    public function test_validacao_da_revisao_normaliza_cpf_e_rejeita_links_invalidos(): void
    {
        $uuid = $this->importar();

        $this->confirmar($uuid, $this->dadosConfirmacao(['links' => 'javascript:alert(1)']))
            ->assertSessionHasErrors('links.0');

        $this->confirmar($uuid, $this->dadosConfirmacao(['cpf' => '529.982.247-25']))->assertRedirect();
        $this->assertSame('52998224725', Candidato::query()->sole()->cpf);
    }

    public function test_importacao_expirada_retorna_404_com_link_para_enviar_novamente(): void
    {
        $uuid = (string) Str::uuid();

        $this->get(route('importacoes.show', $uuid))
            ->assertNotFound()
            ->assertSee('Enviar novamente');

        $this->confirmar($uuid, $this->dadosConfirmacao())->assertNotFound();
        $this->assertSame(0, Candidato::query()->count());
    }

    public function test_rota_de_revisao_aceita_somente_uuid(): void
    {
        $this->get('/candidatos/importar/nao-e-uuid')->assertNotFound();
    }

    public function test_visitante_nao_acessa_a_importacao(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('importacoes.create'))->assertRedirect(route('login'));
        $this->post(route('importacoes.store'), ['arquivo' => $this->pdf(), 'tipo' => 'estagiario'])
            ->assertRedirect(route('login'));
        $this->post(route('competencias.store'), ['nome' => 'Kubernetes', 'categoria' => 'hard'])
            ->assertRedirect(route('login'));
    }

    public function test_sugestao_adicionada_ao_catalogo_volta_marcada_na_revisao(): void
    {
        $uuid = $this->importar();

        $this->from(route('importacoes.show', $uuid))
            ->post(route('competencias.store'), ['nome' => 'Kubernetes', 'categoria' => 'hard', 'importacao' => $uuid])
            ->assertRedirect(route('importacoes.show', $uuid))
            ->assertSessionHas('status', 'Competência adicionada ao catálogo.');

        $kubernetes = Competencia::query()->where('nome', 'Kubernetes')->sole();
        $this->assertSame(['kubernetes'], $kubernetes->sinonimos);
        $this->assertSame('kubernetes', $kubernetes->slug);

        $this->get(route('importacoes.show', $uuid))
            ->assertOk()
            ->assertSee('value="'.$kubernetes->id.'" checked', false);
    }

    public function test_upload_json_devolve_redirect_para_a_revisao(): void
    {
        $resposta = $this->post(route('importacoes.store'), ['arquivo' => $this->pdf(), 'tipo' => 'estagiario'], ['Accept' => 'application/json']);

        $resposta->assertOk()->assertJsonPath('fragments', [])->assertHeader('Cache-Control', 'no-store, private');
        $uuid = Str::afterLast($resposta->json('redirect'), '/');
        $this->assertSame(route('importacoes.show', $uuid), $resposta->json('redirect'));
        $this->assertIsString($resposta->json('message'));
        $this->get(route('importacoes.show', $uuid))->assertOk();
    }

    public function test_upload_json_invalido_retorna_422_sem_criar_importacao(): void
    {
        $this->post(route('importacoes.store'), [
            'arquivo' => UploadedFile::fake()->createWithContent('curriculo.txt', 'texto'),
            'tipo' => 'estagiario',
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.arquivo.0', 'Envie um arquivo PDF de até 10 MB.');

        $this->assertSame([], Storage::disk('curriculos')->allFiles());
    }

    public function test_confirmar_json_devolve_redirect_para_o_perfil_depois_de_salvar(): void
    {
        $uuid = $this->importar();

        $resposta = $this->confirmar($uuid, $this->dadosConfirmacao(), json: true);

        $candidato = Candidato::query()->sole();
        $resposta->assertOk()
            ->assertJson([
                'message' => 'Candidato salvo no banco de talentos.',
                'fragments' => [],
                'redirect' => route('candidatos.show', $candidato),
            ]);
    }

    public function test_confirmar_json_com_erro_retorna_422_e_nao_cria_candidato(): void
    {
        $uuid = $this->importar();

        $this->confirmar($uuid, $this->dadosConfirmacao(['nome' => '', 'consentimento_em' => '']), json: true)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nome', 'consentimento_em']);

        $this->assertSame(0, Candidato::query()->count());
        $this->get(route('importacoes.show', $uuid))->assertOk();
    }

    public function test_confirmar_json_com_importacao_expirada_retorna_404(): void
    {
        $this->confirmar((string) Str::uuid(), $this->dadosConfirmacao(), json: true)
            ->assertNotFound()
            ->assertJsonPath('message', 'O recurso solicitado não foi encontrado.');
    }

    public function test_competencia_json_devolve_somente_o_catalogo_com_texto_escapado(): void
    {
        $uuid = $this->importar();

        $resposta = $this->post(route('competencias.store'), [
            'nome' => '<b>Kube</b>',
            'categoria' => 'hard',
            'importacao' => $uuid,
        ], ['Accept' => 'application/json']);

        $resposta->assertOk()
            ->assertJsonPath('message', 'Competência adicionada ao catálogo.')
            ->assertJsonPath('redirect', null)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['catalogo-competencias'], array_keys($resposta->json('fragments')));

        $html = $resposta->json('fragments.catalogo-competencias');
        $nova = Competencia::query()->where('nome', '<b>Kube</b>')->sole();
        $this->assertStringContainsString('&lt;b&gt;Kube&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<b>Kube</b>', $html);
        $this->assertStringContainsString('data-chave="'.$nova->id.'"', $html);
        $this->assertStringNotContainsString('Confirmar e salvar', $html);
        $this->assertStringNotContainsString('ana.lima@exemplo.com.br', $html);
        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_competencia_duplicada_ou_invalida_retorna_422(): void
    {
        $this->post(route('competencias.store'), ['nome' => 'Python', 'categoria' => 'hard'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nome');

        $this->post(route('competencias.store'), ['nome' => 'Kubernetes', 'categoria' => 'outra'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('categoria');

        $this->assertFalse(Competencia::query()->where('nome', 'Kubernetes')->exists());
    }

    public function test_arquivos_de_demonstracao_sao_gerados_e_lidos_pela_importacao(): void
    {
        $diretorio = storage_path('framework/testing/demo-curriculos-'.Str::random(6));

        $this->artisan('demo:curriculos', ['--diretorio' => $diretorio])->assertSuccessful();

        foreach (['demo-curriculo.pdf', 'demo-escaneado.pdf', 'demo-duplicado.pdf'] as $arquivo) {
            $this->assertFileExists("{$diretorio}/{$arquivo}");
        }

        $uuid = $this->importar(new UploadedFile("{$diretorio}/demo-curriculo.pdf", 'demo-curriculo.pdf', 'application/pdf', null, true));
        $revisao = $this->get(route('importacoes.show', $uuid))->assertOk();

        foreach (['Python', 'SQL', 'Git', 'Inglês intermediário', 'Trabalho em equipe', 'Comunicação'] as $nome) {
            $revisao->assertSee('value="'.$this->idCompetencia($nome).'" checked', false);
        }

        $revisao->assertSee('Kubernetes')->assertSee('https://github.com/', false);

        $this->followRedirects($this->post(route('importacoes.store'), [
            'arquivo' => new UploadedFile("{$diretorio}/demo-escaneado.pdf", 'demo-escaneado.pdf', 'application/pdf', null, true),
            'tipo' => 'servidor',
        ]))->assertSee('parece digitalizado');

        array_map('unlink', glob("{$diretorio}/*.pdf"));
        rmdir($diretorio);
    }
}
