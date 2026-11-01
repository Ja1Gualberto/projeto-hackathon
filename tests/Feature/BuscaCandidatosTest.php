<?php

namespace Tests\Feature;

use App\Enums\Area;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use Database\Seeders\CandidatoSeeder;
use Database\Seeders\CompetenciaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BuscaCandidatosTest extends TestCase
{
    use RefreshDatabase;

    public function test_competencias_sao_combinadas_com_e(): void
    {
        $python = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $sql = Competencia::factory()->hard()->create(['nome' => 'SQL']);
        $completo = Candidato::factory()->create(['nome' => 'Perfil completo']);
        $parcial = Candidato::factory()->create(['nome' => 'Perfil parcial']);
        $completo->competencias()->attach([$python->id, $sql->id], ['origem' => 'curriculo']);
        $parcial->competencias()->attach($python, ['origem' => 'curriculo']);

        $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['competencias' => [$python->id, $sql->id]]))
            ->assertOk()->assertSee($completo->nome)->assertDontSee($parcial->nome)
            ->assertViewHas('candidatos', fn ($resultado): bool => $resultado->total() === 1);
    }

    public function test_tipo_area_e_experiencia_restringem_a_lista_juntos(): void
    {
        $esperado = Candidato::factory()->estagiario()->create([
            'area' => Area::Desenvolvimento, 'experiencia_na_area' => true,
        ]);
        Candidato::factory()->servidor()->create(['area' => Area::Desenvolvimento, 'experiencia_na_area' => true]);
        Candidato::factory()->estagiario()->create(['area' => Area::Dados, 'experiencia_na_area' => true]);
        Candidato::factory()->estagiario()->create(['area' => Area::Desenvolvimento, 'experiencia_na_area' => false]);

        $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['tipo' => 'estagiario', 'area' => 'desenvolvimento', 'experiencia' => 1]))
            ->assertOk()->assertSee($esperado->nome)
            ->assertViewHas('candidatos', fn ($resultado): bool => $resultado->total() === 1);
    }

    public function test_desativados_nao_aparecem_mesmo_quando_o_email_corresponde_a_busca(): void
    {
        $oculto = Candidato::factory()->desativado()->create(['email' => 'oculto@exemplo.com.br']);

        $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['busca' => 'oculto@exemplo.com.br']))
            ->assertOk()->assertDontSee($oculto->nome)->assertSee('0 talentos encontrados');
    }

    public function test_idioma_inclui_niveis_superiores_mas_nao_inferiores_ou_outro_grupo(): void
    {
        $basico = Competencia::factory()->idioma('ingles', 1)->create();
        $avancado = Competencia::factory()->idioma('ingles', 3)->create();
        $espanhol = Competencia::factory()->idioma('espanhol', 3)->create();
        $fluente = Candidato::factory()->create();
        $iniciante = Candidato::factory()->create();
        $outroIdioma = Candidato::factory()->create();
        $fluente->competencias()->attach($avancado, ['origem' => 'curriculo']);
        $iniciante->competencias()->attach($basico, ['origem' => 'curriculo']);
        $outroIdioma->competencias()->attach($espanhol, ['origem' => 'curriculo']);
        $this->actingAs(User::factory()->create());

        $this->get(route('candidatos.index', ['competencias' => [$basico->id]]))
            ->assertOk()->assertSee($fluente->nome)->assertSee($iniciante->nome)
            ->assertDontSee($outroIdioma->nome)->assertSee('2 talentos encontrados');
        $this->get(route('candidatos.index', ['competencias' => [$avancado->id]]))
            ->assertOk()->assertSee($fluente->nome)->assertDontSee($iniciante->nome);
    }

    #[TestWith(['Marina'])]
    #[TestWith(['contato@exemplo.com.br'])]
    public function test_busca_encontra_por_parte_do_nome_ou_email(string $busca): void
    {
        $candidato = Candidato::factory()->create(['nome' => 'Marina Teste', 'email' => 'contato@exemplo.com.br']);
        $outro = Candidato::factory()->create(['nome' => 'Outro perfil', 'email' => 'outro@exemplo.com.br']);

        $this->actingAs(User::factory()->create())->get(route('candidatos.index', ['busca' => $busca]))
            ->assertOk()->assertSee($candidato->nome)->assertDontSee($outro->nome);
    }

    public function test_cards_nao_exibem_codigos_e_distinguem_experiencia_nao_informada(): void
    {
        $candidato = Candidato::factory()->estagiario()->create(['experiencia_na_area' => false]);

        $this->actingAs(User::factory()->create())->get(route('candidatos.index'))
            ->assertOk()->assertSee($candidato->nome)->assertSee('Estagiário')
            ->assertSee('Experiência na área: não informado')->assertDontSee('CAND-');
    }

    public function test_filtros_sem_resultado_oferecem_remover_filtros(): void
    {
        $this->actingAs(User::factory()->create())->get(route('candidatos.index', ['busca' => 'Inexistente']))
            ->assertOk()->assertSee('0 talentos encontrados')->assertSee('Remover filtros')
            ->assertSee('Nenhum talento com esses critérios');
    }

    public function test_paginacao_preserva_filtros_e_ordem_alfabetica(): void
    {
        Candidato::factory()->estagiario()->count(13)->sequence(
            fn ($sequencia): array => ['nome' => 'Talento '.str_pad((string) $sequencia->index, 2, '0', STR_PAD_LEFT)],
        )->create();

        $resposta = $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['tipo' => 'estagiario']));

        $resposta->assertOk()->assertSee('13 talentos encontrados')
            ->assertSeeInOrder(['Talento 00', 'Talento 11'])->assertDontSee('Talento 12');
        $this->assertStringContainsString('tipo=estagiario', $resposta['candidatos']->nextPageUrl());
    }

    #[TestWith([['tipo' => 'invalido'], 'tipo'])]
    #[TestWith([['area' => 'invalida'], 'area'])]
    #[TestWith([['competencias' => ['999999']], 'competencias.0'])]
    #[TestWith([['competencias' => 'texto'], 'competencias'])]
    #[TestWith([['experiencia' => 'talvez'], 'experiencia'])]
    #[TestWith([['situacao' => 'todos'], 'situacao'])]
    public function test_filtros_invalidos_sao_rejeitados(array $parametros, string $campo): void
    {
        $this->actingAs(User::factory()->create())->from(route('candidatos.index'))
            ->get(route('candidatos.index', $parametros))->assertSessionHasErrors($campo);
        $this->getJson(route('candidatos.index', $parametros))->assertUnprocessable()
            ->assertJsonValidationErrors($campo)->assertHeader('Cache-Control', 'no-store, private');
    }

    #[TestWith([1, 'Talento 00', 'Talento 12'])]
    #[TestWith([2, 'Talento 12', 'Talento 00'])]
    public function test_busca_json_preserva_filtros_contagem_ordem_e_paginacao_do_html(int $pagina, string $presente, string $ausente): void
    {
        $python = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $basico = Competencia::factory()->idioma('ingles', 1)->create();
        $avancado = Competencia::factory()->idioma('ingles', 3)->create();
        $perfis = Candidato::factory()->estagiario()->count(13)->sequence(
            fn ($sequencia): array => ['nome' => 'Talento '.str_pad((string) $sequencia->index, 2, '0', STR_PAD_LEFT)],
        )->create(['area' => Area::Desenvolvimento, 'experiencia_na_area' => true]);
        foreach ($perfis as $perfil) {
            $perfil->competencias()->attach([$python->id, $avancado->id], ['origem' => 'curriculo']);
        }
        foreach ([
            ['tipo' => 'servidor'], ['area' => Area::Dados], ['experiencia_na_area' => false],
            ['status' => 'desativado'], ['nome' => 'Outra pessoa'],
        ] as $diferenca) {
            $outro = Candidato::factory()->estagiario()->create(array_merge([
                'nome' => 'Talento excluído', 'area' => Area::Desenvolvimento, 'experiencia_na_area' => true,
            ], $diferenca));
            $outro->competencias()->attach([$python->id, $avancado->id], ['origem' => 'curriculo']);
        }
        $parcial = Candidato::factory()->estagiario()->create([
            'nome' => 'Talento parcial', 'area' => Area::Desenvolvimento, 'experiencia_na_area' => true,
        ]);
        $parcial->competencias()->attach($python->id, ['origem' => 'curriculo']);
        $url = route('candidatos.index', [
            'competencias' => [$python->id, $basico->id], 'tipo' => 'estagiario',
            'area' => 'desenvolvimento', 'experiencia' => 1, 'busca' => 'Talento', 'page' => $pagina,
        ]);
        $this->actingAs(User::factory()->create());

        $html = $this->get($url)->assertOk()->assertSee($presente)->assertDontSee($ausente)
            ->assertSee('13 talentos encontrados')->assertHeader('Cache-Control', 'no-store, private');
        $json = $this->getJson($url)->assertOk()->assertJsonPath('message', null)->assertJsonPath('redirect', null)
            ->assertJsonCount(1, 'fragments')->assertHeader('Cache-Control', 'no-store, private');
        $fragmento = $json->json('fragments.resultados-candidatos');

        $this->assertIsString($fragmento);
        $this->assertStringContainsString($fragmento, $html->getContent());
        $this->assertStringContainsString($presente, $fragmento);
        $this->assertStringContainsString('13 talentos encontrados', $fragmento);
        $this->assertStringContainsString('tipo=estagiario', $fragmento);
        $this->assertStringContainsString('experiencia=1', $fragmento);
        $this->assertStringContainsString('competencias%5B0%5D=', $fragmento);
        foreach ([$ausente, 'Talento excluído', 'Talento parcial', 'CAND-', '<html', '<form', '<script'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $fragmento);
        }
    }

    public function test_busca_json_vazia_oferece_link_real_e_escapa_identidade_nos_cards(): void
    {
        $candidato = Candidato::factory()->create(['nome' => '<script>alert(1)</script>']);
        $this->actingAs(User::factory()->create());

        $vazio = $this->getJson(route('candidatos.index', ['busca' => 'Inexistente']))->assertOk()
            ->json('fragments.resultados-candidatos');
        $this->assertIsString($vazio);
        $this->assertStringContainsString('0 talentos encontrados', $vazio);
        $this->assertStringContainsString('Remover filtros', $vazio);
        $this->assertStringContainsString('href="'.route('candidatos.index').'"', $vazio);
        $fragmento = $this->getJson(route('candidatos.index'))->assertOk()->json('fragments.resultados-candidatos');
        $this->assertStringContainsString(e($candidato->nome), $fragmento);
        $this->assertStringNotContainsString($candidato->nome, $fragmento);
    }

    public function test_visitante_json_recebe_401_sem_redirecionar_e_recurso_ausente_recebe_404(): void
    {
        $this->getJson(route('candidatos.index'))->assertUnauthorized()
            ->assertExactJson(['message' => 'Sua sessão expirou. Entre novamente para continuar.'])
            ->assertHeaderMissing('Location');
        $this->get(route('candidatos.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->getJson('/candidatos/999999')->assertNotFound()
            ->assertExactJson(['message' => 'O recurso solicitado não foi encontrado.']);
    }

    public function test_perfil_mostra_secoes_origens_cpf_mascarado_e_nao_exibe_codigo(): void
    {
        $candidato = Candidato::factory()->create([
            'cpf' => '12345678909', 'formacoes' => ['Sistemas de Informação'],
            'experiencias' => [], 'projetos' => ['Portal de serviços'],
            'links' => ['https://example.com/portfolio'],
        ]);
        $competencia = Competencia::factory()->soft()->create(['nome' => 'Comunicação']);
        $candidato->competencias()->attach($competencia, ['origem' => 'entrevista']);

        $this->actingAs(User::factory()->create())->get(route('candidatos.show', $candidato))
            ->assertOk()->assertSee($candidato->nome)->assertSee('Sistemas de Informação')
            ->assertSee('Portal de serviços')->assertSee('https://example.com/portfolio')
            ->assertSee('observada')->assertSee('***.***.***-09')
            ->assertSee('Não informado no currículo')->assertDontSee($candidato->cpf)->assertDontSee('CAND-');
    }

    public function test_perfil_escapa_texto_e_nao_cria_links_executaveis(): void
    {
        $texto = '<script>alert(1)</script>';
        $candidato = Candidato::factory()->create([
            'nome' => $texto, 'formacoes' => [$texto], 'experiencias' => [$texto], 'projetos' => [$texto],
            'links' => ['javascript:alert(1)'],
        ]);

        $this->actingAs(User::factory()->create())->get(route('candidatos.show', $candidato))
            ->assertOk()->assertSee($texto)->assertDontSee($texto, false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_pdf_privado_e_servido_inline_sem_cache(): void
    {
        Storage::fake('curriculos');
        Storage::disk('curriculos')->put('curriculo.pdf', '%PDF-1.4 teste');
        $candidato = Candidato::factory()->create([
            'curriculo_arquivo' => 'curriculo.pdf', 'curriculo_nome_original' => 'perfil.pdf',
        ]);

        $this->actingAs(User::factory()->create())->get(route('candidatos.curriculo', $candidato))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=perfil.pdf')
            ->assertHeader('Cache-Control', 'no-store, private');

        Storage::disk('curriculos')->assertExists('curriculo.pdf');
    }

    #[TestWith([null])]
    #[TestWith(['inexistente.pdf'])]
    public function test_pdf_ausente_retorna_404(?string $arquivo): void
    {
        Storage::fake('curriculos');
        $candidato = Candidato::factory()->create(['curriculo_arquivo' => $arquivo]);

        $this->actingAs(User::factory()->create())->get(route('candidatos.curriculo', $candidato))->assertNotFound();
    }

    public function test_visitante_nao_acessa_busca_perfil_nem_pdf(): void
    {
        $candidato = Candidato::factory()->create();

        $this->get(route('candidatos.index'))->assertRedirect(route('login'));
        $this->get(route('candidatos.show', $candidato))->assertRedirect(route('login'));
        $this->get(route('candidatos.curriculo', $candidato))->assertRedirect(route('login'));
    }

    public function test_rota_de_perfil_aceita_apenas_id_numerico(): void
    {
        $this->actingAs(User::factory()->create())->get('/candidatos/importar')->assertNotFound();
    }

    public function test_demo_tem_24_perfis_com_pdf_e_sete_estagiarios_com_python_e_sql(): void
    {
        Storage::fake('curriculos');
        $this->freezeTime();
        $this->seed([CompetenciaSeeder::class, CandidatoSeeder::class]);
        $ids = Competencia::query()->whereIn('nome', ['Python', 'SQL'])->pluck('id')->all();

        $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['competencias' => $ids, 'tipo' => 'estagiario']))
            ->assertOk()->assertSee('7 talentos encontrados');

        $this->assertDatabaseCount('candidatos', 24);
        Storage::disk('curriculos')->assertCount('/', 24);
        $this->assertDatabaseHas('candidatos', ['email' => 'ana.lima@exemplo.com.br']);
        $this->assertSame(3, Candidato::comRetencaoVencida()->count());
    }

    public function test_reexecutar_o_seeder_nao_duplica_pessoas_nem_pdfs(): void
    {
        Storage::fake('curriculos');
        $this->freezeTime();
        $this->seed([CompetenciaSeeder::class, CandidatoSeeder::class]);

        $this->seed(CandidatoSeeder::class);

        $this->assertDatabaseCount('candidatos', 24);
        Storage::disk('curriculos')->assertCount('/', 24);
        $this->assertSame(12, Candidato::where('tipo', 'servidor')->count());
        $this->assertSame(12, Candidato::where('tipo', 'estagiario')->count());
        $this->assertDatabaseMissing('candidato_competencia', ['origem' => 'entrevista']);
    }

    public function test_catalogo_agrupa_por_categoria_e_busca_apenas_os_ids_pedidos(): void
    {
        $python = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $git = Competencia::factory()->hard()->create(['nome' => 'Git']);
        $comunicacao = Competencia::factory()->soft()->create(['nome' => 'Comunicação']);
        $repository = $this->app->make(CompetenciaRepositoryInterface::class);

        $catalogo = $repository->todasAgrupadasPorCategoria();

        $this->assertSame([$git->id, $python->id], $catalogo['hard']->modelKeys());
        $this->assertSame([$comunicacao->id], $catalogo['soft']->modelKeys());
        $this->assertSame([$python->id], $repository->buscarPorIds([$python->id])->modelKeys());
        $this->assertSame([], $repository->buscarPorIds([])->modelKeys());
    }

    public function test_duplicidade_por_email_ou_cpf_nao_busca_campos_vazios(): void
    {
        $candidato = Candidato::factory()->desativado()->create([
            'email' => 'duplicado@exemplo.com.br', 'cpf' => '12345678909',
        ]);
        Candidato::factory()->create(['email' => null, 'cpf' => null]);
        $repository = $this->app->make(CandidatoRepositoryInterface::class);

        $this->assertTrue($repository->encontrarPorEmailOuCpf($candidato->email, null)->is($candidato));
        $this->assertTrue($repository->encontrarPorEmailOuCpf(null, $candidato->cpf)->is($candidato));
        $this->assertNull($repository->encontrarPorEmailOuCpf(null, null));
        $this->assertNull($repository->encontrarPorEmailOuCpf('', ''));
        $this->assertNull($repository->encontrarPorEmailOuCpf('outro@exemplo.com.br', null));
    }
}
