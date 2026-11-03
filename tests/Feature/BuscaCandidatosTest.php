<?php

namespace Tests\Feature;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\Area;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\CompetenciaRepositoryInterface;
use App\Services\ApresentacaoCandidatosService;
use Database\Seeders\CandidatoSeeder;
use Database\Seeders\CompetenciaSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
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

    public function test_processo_area_e_experiencia_restringem_a_lista_juntos(): void
    {
        $esperado = Candidato::factory()->estagiario()->create([
            'area' => Area::Desenvolvimento, 'experiencia_na_area' => true,
        ]);
        Candidato::factory()->servidor()->create(['area' => Area::Desenvolvimento, 'experiencia_na_area' => true]);
        Candidato::factory()->estagiario()->create(['area' => Area::Dados, 'experiencia_na_area' => true]);
        Candidato::factory()->estagiario()->create(['area' => Area::Desenvolvimento, 'experiencia_na_area' => false]);
        $vaga = Vaga::factory()->create();
        $vaga->participacoes()->create(['candidato_id' => $esperado->id, 'etapa' => 'triagem']);

        $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['vaga_id' => $vaga->id, 'area' => 'desenvolvimento', 'experiencia' => 1]))
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
            ->assertOk()->assertSee($candidato->nome)->assertDontSee('Sem processo seletivo vinculado')
            ->assertSee('Experiência na área: não informado')->assertDontSee('CAND-');
    }

    public function test_cards_agrupam_nome_experiencia_e_apenas_a_ultima_vaga_antes_do_link_de_perfil(): void
    {
        $this->freezeTime();
        $candidato = Candidato::factory()->create(['area' => Area::Desenvolvimento, 'experiencia_na_area' => true]);
        $vaga = Vaga::factory()->create(['titulo' => 'Estagiário de Infraestrutura']);
        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $vagaAnterior = Vaga::factory()->create(['titulo' => 'Vaga anterior']);
        $participacaoAnterior = $vagaAnterior->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $participacaoAnterior->forceFill(['created_at' => now()->subDay()])->save();

        $resposta = $this->actingAs(User::factory()->create())
            ->getJson(route('candidatos.index'))->assertOk();
        $fragmento = $resposta->json('fragments.resultados-candidatos');

        $this->assertStringContainsString($candidato->nome, $fragmento);
        $this->assertStringContainsString('Ver perfil', $fragmento);
        $this->assertStringContainsString('Última vaga: '.$vaga->titulo, $fragmento);
        $this->assertStringNotContainsString($vagaAnterior->titulo, $fragmento);

        $documento = new \DOMDocument;
        $documento->loadHTML($fragmento, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $cabecalho = '//article[@data-card-candidato]/a';
        $this->assertCount(1, $xpath->query($cabecalho.'/div[h3][p][span[not(@hidden)]]'));
        $this->assertCount(1, $xpath->query($cabecalho.'/div/*[1][self::h3]'));
        $this->assertCount(1, $xpath->query($cabecalho.'/*[2][self::span]'));
        $this->get(route('candidatos.index'))->assertOk()->assertSeeInOrder([
            $candidato->nome, 'Desenvolvimento', 'Experiência na área: sim', 'Última vaga: '.$vaga->titulo, 'Ver perfil',
        ]);
    }

    public function test_filtros_sem_resultado_oferecem_remover_filtros(): void
    {
        $this->actingAs(User::factory()->create())->get(route('candidatos.index', ['busca' => 'Inexistente']))
            ->assertOk()->assertSee('0 talentos encontrados')->assertSee('Remover filtros')
            ->assertSee('Nenhum talento com esses critérios');
    }

    public function test_elementos_opcionais_ficam_ocultos_e_nao_ativam_alertas_sem_erros(): void
    {
        $candidato = Candidato::factory()->create(['dados_atualizados_em' => now()]);
        $competencia = Competencia::factory()->hard()->create();
        $candidato->competencias()->attach($competencia, ['origem' => 'curriculo']);

        $resposta = $this->actingAs(User::factory()->create())->get(route('candidatos.index'))->assertOk();

        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $card = '//article[@data-card-candidato][a[@href="'.route('candidatos.show', $candidato).'"]]';
        $this->assertCount(2, $xpath->query($card.'/div[@hidden]'));
        $this->assertCount(1, $xpath->query($card.'//p[@hidden]'));
        $this->assertCount(1, $xpath->query($card.'//button[@data-expandir-competencias][@hidden][@aria-controls="competencias-candidato-'.$candidato->id.'"]'));
        $this->assertCount(0, $xpath->query($card.'//a//button'));
        $this->assertCount(1, $xpath->query('//div[@aria-label="Competências selecionadas"][@hidden]'));
        $this->assertCount(1, $xpath->query('//main/div[@role="alert"][@hidden][not(@data-alerta)]'));
    }

    public function test_html_entrega_filtros_e_catalogo_preenchidos_sem_depender_de_javascript(): void
    {
        $competencia = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $candidato = Candidato::factory()->estagiario()->retencaoVencida()->create([
            'nome' => 'Marina Teste', 'area' => Area::Dados, 'experiencia_na_area' => true,
        ]);
        $candidato->competencias()->attach($competencia, ['origem' => 'curriculo']);

        $vaga = Vaga::factory()->create();
        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $resposta = $this->actingAs(User::factory()->create())->get(route('candidatos.index', [
            'busca' => 'Marina', 'situacao' => 'retencao', 'vaga_id' => $vaga->id,
            'area' => 'dados', 'experiencia' => 1, 'competencias' => [$competencia->id],
        ]));

        $resposta->assertOk()->assertSee('1 talento encontrado')->assertSee($candidato->nome)
            ->assertSee('Perfis ativos com retenção pendente')->assertSee('Com todas:')
            ->assertSee('xl:grid-cols-[15rem_minmax(0,1fr)]', false)
            ->assertSee('grid grid-cols-1 items-start gap-3 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4', false);
        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        foreach (['situacao' => 'retencao', 'vaga_id' => $vaga->id, 'area' => 'dados'] as $nome => $valor) {
            $this->assertCount(1, $xpath->query('//select[@name="'.$nome.'"]/option[@value="'.$valor.'"][@selected]'));
        }
        $this->assertCount(1, $xpath->query('//input[@name="busca"][@value="Marina"]'));
        $this->assertCount(1, $xpath->query('//input[@name="experiencia"][@checked]'));
        $this->assertCount(1, $xpath->query('//input[@name="competencias[]"][@value="'.$competencia->id.'"][@checked]'));
        $this->assertCount(1, $xpath->query('//form[@method="GET"][@data-busca-candidatos]'));
        $this->assertCount(1, $xpath->query('//section[@id="resultados-candidatos"]'));
    }

    public function test_cards_entregam_todas_as_competencias_para_expandir_e_preservam_avisos_de_situacao(): void
    {
        $candidato = Candidato::factory()->desativado()->retencaoVencida()->create();
        $competencias = Competencia::factory()->hard()->count(6)->sequence(
            fn ($sequencia): array => ['nome' => 'Competência '.$sequencia->index],
        )->create();
        $candidato->competencias()->attach($competencias->modelKeys(), ['origem' => 'curriculo']);

        $resposta = $this->actingAs(User::factory()->create())
            ->getJson(route('candidatos.index', ['situacao' => 'desativados']));

        $resposta->assertOk();
        $fragmento = $resposta->json('fragments.resultados-candidatos');
        $this->assertStringContainsString('Desativado', $fragmento);
        $this->assertStringContainsString('revisão de retenção pendente', $fragmento);
        $this->assertStringContainsString('Ver todas', $fragmento);
        $this->assertStringContainsString('Competência 4', $fragmento);
        $this->assertStringContainsString('Competência 5', $fragmento);
        $this->assertStringContainsString('href="'.route('candidatos.show', $candidato).'"', $fragmento);
        $documento = new \DOMDocument;
        $documento->loadHTML($fragmento, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(6, $xpath->query('//div[@id="competencias-candidato-'.$candidato->id.'"][@data-expandido="true"]/span[@data-competencia-card][not(@hidden)]'));
    }

    public function test_card_sem_competencias_mostra_mensagem_sem_tags(): void
    {
        Candidato::factory()->create();

        $resposta = $this->actingAs(User::factory()->create())->getJson(route('candidatos.index'))->assertOk();

        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->json('fragments.resultados-candidatos'), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(1, $xpath->query('//div[@data-competencias-card]/p[not(@hidden)]'));
        $this->assertCount(0, $xpath->query('//span[@data-competencia-card]'));
        $this->assertCount(1, $xpath->query('//button[@data-expandir-competencias][@hidden]'));
    }

    public function test_preenchimento_anterior_e_escapado_no_html(): void
    {
        $texto = '\"><script>alert(1)</script>';

        $resposta = $this->actingAs(User::factory()->create())
            ->withSession(['_old_input' => ['busca' => $texto]])->get(route('candidatos.index'));

        $resposta->assertOk()->assertSee($texto)->assertDontSee($texto, false);
    }

    public function test_apresentacao_escapa_resumo_de_erros_e_preserva_associacao_com_o_campo(): void
    {
        $texto = '<script>alert(1)</script>';
        $erros = (new ViewErrorBag)->put('default', new MessageBag(['busca' => $texto]));
        $this->app['view']->share('errors', $erros);
        $catalogo = ['hard' => new Collection, 'soft' => new Collection];

        $dados = $this->app->make(ApresentacaoCandidatosService::class)->filtros(FiltroCandidatosDTO::fromArray([]), $catalogo, $erros);

        $this->assertStringContainsString(e($texto), $dados['errosFiltros']->toHtml());
        $this->assertStringNotContainsString($texto, $dados['errosFiltros']->toHtml());
        $this->assertTrue($dados['alertaErros']->get('data-alerta'));
        $this->assertFalse($dados['alertaErros']->get('hidden'));
        $this->assertStringContainsString('aria-invalid="true"', $dados['campos']['busca']->toHtml());
        $this->assertStringContainsString('aria-describedby="campo-busca-erro"', $dados['campos']['busca']->toHtml());
    }

    public function test_competencias_sao_escapadas_no_catalogo_nos_filtros_selecionados_e_nos_cards(): void
    {
        $texto = '<img src=x onerror=alert(1)>';
        $competencia = Competencia::factory()->hard()->create(['nome' => $texto]);
        $candidato = Candidato::factory()->create();
        $candidato->competencias()->attach($competencia, ['origem' => 'curriculo']);
        $url = route('candidatos.index', ['competencias' => [$competencia->id]]);
        $this->actingAs(User::factory()->create());

        $html = $this->get($url)->assertOk()->assertSee($texto)->assertDontSee($texto, false);
        $fragmento = $this->getJson($url)->assertOk()->json('fragments.resultados-candidatos');

        $this->assertStringContainsString(e($texto), $fragmento);
        $this->assertStringNotContainsString($texto, $fragmento);
        $this->assertStringContainsString($fragmento, $html->getContent());
    }

    public function test_paginacao_preserva_filtros_e_ordem_alfabetica(): void
    {
        Candidato::factory()->estagiario()->count(13)->sequence(
            fn ($sequencia): array => ['nome' => 'Talento '.str_pad((string) $sequencia->index, 2, '0', STR_PAD_LEFT)],
        )->create();

        $resposta = $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['tipo' => 'estagiario']));

        $resposta->assertOk()->assertSee('13 talentos encontrados')
            ->assertSeeInOrder(['Talento 00', 'Talento 09'])->assertDontSee('Talento 10')
            ->assertViewHas('candidatos', fn ($resultado): bool => $resultado->perPage() === 10 && $resultado->count() === 10);
        $this->assertStringContainsString('tipo=estagiario', $resposta['candidatos']->nextPageUrl());
    }

    #[TestWith([10])]
    #[TestWith([20])]
    #[TestWith([50])]
    #[TestWith([100])]
    public function test_quantidade_por_pagina_limita_cards_e_preserva_selecao_e_filtros(int $porPagina): void
    {
        Candidato::factory()->count($porPagina + 1)->sequence(
            fn ($sequencia): array => ['nome' => 'Talento '.str_pad((string) $sequencia->index, 3, '0', STR_PAD_LEFT)],
        )->create(['area' => Area::Dados]);
        $url = route('candidatos.index', ['area' => 'dados', 'busca' => 'Talento', 'por_pagina' => $porPagina]);
        $this->actingAs(User::factory()->create());

        $resposta = $this->get($url)->assertOk();

        $this->assertSame($porPagina, $resposta['candidatos']->perPage());
        $this->assertCount($porPagina, $resposta['candidatos']);
        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(4, $xpath->query('//select[@name="por_pagina"]/option'));
        $this->assertCount(1, $xpath->query('//select[@name="por_pagina"]/option[@value="'.$porPagina.'"][@selected]'));

        $proximaUrl = $resposta['candidatos']->nextPageUrl();
        $this->assertStringContainsString('por_pagina='.$porPagina, $proximaUrl);
        $this->assertStringContainsString('area=dados', $proximaUrl);
        $this->assertStringContainsString('busca=Talento', $proximaUrl);
        $this->get($proximaUrl)->assertOk()
            ->assertViewHas('candidatos', fn ($resultado): bool => $resultado->count() === 1 && $resultado->currentPage() === 2);

        $fragmento = $this->getJson($url)->assertOk()->json('fragments.resultados-candidatos');
        $documento->loadHTML($fragmento, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $this->assertCount($porPagina, (new \DOMXPath($documento))->query('//article[@data-card-candidato]'));
    }

    #[TestWith([['vaga_id' => 'invalido'], 'vaga_id'])]
    #[TestWith([['area' => 'invalida'], 'area'])]
    #[TestWith([['competencias' => ['999999']], 'competencias.0'])]
    #[TestWith([['competencias' => 'texto'], 'competencias'])]
    #[TestWith([['experiencia' => 'talvez'], 'experiencia'])]
    #[TestWith([['situacao' => 'todos'], 'situacao'])]
    #[TestWith([['por_pagina' => 12], 'por_pagina'])]
    #[TestWith([['por_pagina' => 0], 'por_pagina'])]
    #[TestWith([['por_pagina' => 101], 'por_pagina'])]
    #[TestWith([['por_pagina' => 'texto'], 'por_pagina'])]
    #[TestWith([['por_pagina' => [10]], 'por_pagina'])]
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
        $vaga = Vaga::factory()->create();
        foreach ($perfis as $perfil) {
            $vaga->participacoes()->create(['candidato_id' => $perfil->id, 'etapa' => 'triagem']);
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
            'competencias' => [$python->id, $basico->id], 'vaga_id' => $vaga->id,
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
        $this->assertStringContainsString('vaga_id='.$vaga->id, $fragmento);
        $this->assertStringContainsString('experiencia=1', $fragmento);
        $this->assertStringContainsString('competencias%5B0%5D=', $fragmento);
        $documento = new \DOMDocument;
        $documento->loadHTML($fragmento, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(1, $xpath->query('//div[@data-paginacao-busca]/nav'));
        $relacao = $pagina === 1 ? 'next' : 'prev';
        $outraPagina = $pagina === 1 ? 2 : 1;
        $this->assertGreaterThan(0, $xpath->query('//div[@data-paginacao-busca]//a[@rel="'.$relacao.'"][contains(@href, "page='.$outraPagina.'")]')->length);
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
        $this->actingAs(User::factory()->create());

        $this->get('/candidatos/ana-lima')->assertNotFound();
        $this->get('/candidatos/importar')->assertOk()->assertSee('Ler currículo');
    }

    public function test_demo_tem_24_perfis_com_pdf_e_busca_competencias_sem_dividir_por_tipo(): void
    {
        Storage::fake('curriculos');
        $this->freezeTime();
        $this->seed([CompetenciaSeeder::class, CandidatoSeeder::class]);
        $ids = Competencia::query()->whereIn('nome', ['Python', 'SQL'])->pluck('id')->all();

        $this->actingAs(User::factory()->create())
            ->get(route('candidatos.index', ['competencias' => $ids]))
            ->assertOk()->assertViewHas('candidatos', fn ($resultado): bool => $resultado->total() > 7);

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
