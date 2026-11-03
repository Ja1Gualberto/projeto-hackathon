<?php

namespace Tests\Feature;

use App\DTOs\SugestaoEnergiaDTO;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use App\Services\Aderencia\RankingVagaService;
use App\Services\Energia\AnalisadorEnergia;
use App\Services\Energia\AnalisadorEnergiaIndisponivel;
use App\Services\Energia\ClaudeAnalisadorEnergia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PerfilEnergiaTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_analise_grava_sugestao_anonimiza_entrada_e_atualiza_apenas_energia(bool $json): void
    {
        $this->freezeTime();
        $candidato = Candidato::factory()->create([
            'nome' => 'Ágata Queiroz', 'email' => 'agata@exemplo.com.br',
            'telefone' => '(69) 99999-1234', 'cpf' => '12345678901',
            'formacoes' => ['Curso de Ágata Queiroz, agata@exemplo.com.br'],
            'experiencias' => ['QUEIROZ coordenou equipe; (69) 99999-1234; 123.456.789-01'],
            'projetos' => ['Agata: análise de dados https://example.com/agata'],
            'texto_curriculo' => 'TEXTO BRUTO NÃO DEVE SER ENVIADO',
            'links' => ['https://example.com/privado'],
        ]);
        $candidato->competencias()->attach(Competencia::factory()->create(['nome' => 'Python']), ['origem' => 'curriculo']);
        $falso = $this->analisador(new SugestaoEnergiaDTO(3, 4, 1, '<script>evidência</script>'));
        $this->actingAs(User::factory()->create());

        $resposta = $json ? $this->postJson(route('candidatos.energia.analisar', $candidato))
            : $this->post(route('candidatos.energia.analisar', $candidato));

        if ($json) {
            $resposta->assertOk()->assertJsonPath('redirect', null)->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame(['secao-energia'], array_keys($resposta->json('fragments')));
            $html = $resposta->json('fragments.secao-energia');
            $this->assertStringContainsString('&lt;script&gt;evidência&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringContainsString('Sugerido pela IA', $html);
        } else {
            $resposta->assertRedirect(route('candidatos.show', $candidato))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseHas('perfis_energia', [
            'candidato_id' => $candidato->id, 'origem' => 'ia', 'dinamico_analitico' => 3,
            'individual_equipe' => 4, 'execucao_planejamento' => 1, 'revisado_por' => null,
            'analisado_em' => now()->toDateTimeString(),
        ]);
        $this->assertStringContainsString('Python', $falso->recebido);
        $this->assertStringContainsString('coordenou equipe', $falso->recebido);
        foreach (['Ágata', 'Agata', 'Queiroz', 'QUEIROZ', $candidato->email, $candidato->telefone, $candidato->cpf,
            '123.456.789-01', 'https://', 'TEXTO BRUTO', $candidato->codigo] as $proibido) {
            $this->assertStringNotContainsString($proibido, $falso->recebido);
        }
    }

    #[TestWith([false, false])]
    #[TestWith([true, false])]
    #[TestWith([true, true])]
    public function test_indisponibilidade_nao_apaga_nem_modifica_perfil_anterior(bool $json, bool $comPerfil): void
    {
        $candidato = Candidato::factory()->create();
        if ($comPerfil) {
            $this->perfil($candidato, 'humano');
        }
        $this->analisador(null);
        $this->actingAs(User::factory()->create());

        $resposta = $json ? $this->postJson(route('candidatos.energia.analisar', $candidato))
            : $this->post(route('candidatos.energia.analisar', $candidato));

        if ($json) {
            $resposta->assertServiceUnavailable()->assertExactJson(['message' => 'Análise indisponível no momento.'])
                ->assertHeader('Cache-Control', 'no-store, private');
        } else {
            $resposta->assertRedirect(route('candidatos.show', $candidato))->assertSessionHas('status', 'Análise indisponível no momento.');
        }
        $this->assertDatabaseCount('perfis_energia', $comPerfil ? 1 : 0);
        if ($comPerfil) {
            $this->assertDatabaseHas('perfis_energia', ['candidato_id' => $candidato->id, 'origem' => 'humano', 'justificativa' => 'Perfil preservado']);
        }
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_ajuste_manual_grava_revisor_e_ignora_campos_protegidos(bool $json): void
    {
        $this->freezeTime();
        $usuario = User::factory()->create();
        $candidato = Candidato::factory()->create();
        $this->perfil($candidato);
        $this->actingAs($usuario);
        $dados = [...$this->eixos(), 'justificativa' => '<img src=x onerror=alert(1)>', 'origem' => 'ia', 'revisado_por' => 999999];

        $resposta = $json ? $this->putJson(route('candidatos.energia.update', $candidato), $dados)
            : $this->put(route('candidatos.energia.update', $candidato), $dados);

        if ($json) {
            $resposta->assertOk()->assertJsonPath('redirect', null);
            $this->assertStringContainsString('Revisado por humano', $resposta->json('fragments.secao-energia'));
            $this->assertStringNotContainsString('<img ', $resposta->json('fragments.secao-energia'));
        } else {
            $resposta->assertRedirect(route('candidatos.show', $candidato));
        }
        $this->assertDatabaseCount('perfis_energia', 1);
        $this->assertDatabaseHas('perfis_energia', [...$this->eixos(), 'candidato_id' => $candidato->id,
            'origem' => 'humano', 'revisado_por' => $usuario->id, 'analisado_em' => now()->toDateTimeString()]);
    }

    #[TestWith(['dinamico_analitico', null])]
    #[TestWith(['individual_equipe', 0])]
    #[TestWith(['execucao_planejamento', 6])]
    #[TestWith(['dinamico_analitico', 2.5])]
    #[TestWith(['justificativa', ['inválida']])]
    public function test_ajuste_invalido_retorna_422_sem_escrita(string $campo, mixed $valor): void
    {
        $candidato = Candidato::factory()->create();

        $this->actingAs(User::factory()->create())->putJson(route('candidatos.energia.update', $candidato), [
            ...$this->eixos(), $campo => $valor,
        ])->assertUnprocessable()->assertJsonValidationErrors($campo);

        $this->assertDatabaseCount('perfis_energia', 0);
    }

    public function test_justificativa_longa_rejeitada_e_fallback_html_preserva_campos(): void
    {
        $candidato = Candidato::factory()->create();

        $this->actingAs(User::factory()->create())->from(route('candidatos.show', $candidato))
            ->put(route('candidatos.energia.update', $candidato), [...$this->eixos(), 'justificativa' => str_repeat('á', 501)])
            ->assertRedirect(route('candidatos.show', $candidato))->assertSessionHasErrors('justificativa')
            ->assertSessionHasInput('dinamico_analitico', 2);

        $this->assertDatabaseCount('perfis_energia', 0);
    }

    #[TestWith([false, 'chave-ficticia', AnalisadorEnergiaIndisponivel::class])]
    #[TestWith([true, '', AnalisadorEnergiaIndisponivel::class])]
    #[TestWith([true, 'chave-ficticia', ClaudeAnalisadorEnergia::class])]
    public function test_binding_respeita_habilitacao_e_chave(bool $habilitada, string $chave, string $classe): void
    {
        config(['banco_talentos.energia.habilitada' => $habilitada, 'services.anthropic.key' => $chave]);

        $this->assertInstanceOf($classe, $this->app->make(AnalisadorEnergia::class));
    }

    public function test_ranking_mantem_ordem_e_aderencia_com_proximidade_separada_em_html_e_json(): void
    {
        $competencia = Competencia::factory()->create();
        $vaga = Vaga::factory()->create(['tipo_candidato' => 'ambos']);
        $vaga->competencias()->attach($competencia, ['peso' => 3]);
        $primeiro = Candidato::factory()->create();
        $primeiro->competencias()->attach($competencia, ['origem' => 'curriculo']);
        $segundo = Candidato::factory()->create();
        foreach ([$primeiro, $segundo] as $candidato) {
            $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        }
        $this->perfil($primeiro);
        $this->perfil($segundo);
        $segundo->perfilEnergia()->update($this->eixos());
        $ranking = $this->app->make(RankingVagaService::class);
        $antes = $ranking->ranquear($vaga);
        $vaga->update(['energia_dinamico_analitico' => 2, 'energia_individual_equipe' => 4, 'energia_execucao_planejamento' => 2]);

        $depois = $ranking->ranquear($vaga);

        $this->assertSame($antes->pluck('codigo')->all(), $depois->pluck('codigo')->all());
        $this->assertSame([100, 0], $depois->map(fn ($item): int => $item->resultado->percentual)->all());
        $this->assertSame([83, 100], $depois->pluck('proximidadeEnergia')->all());
        $this->actingAs(User::factory()->create())->get(route('vagas.show', $vaga))->assertOk()
            ->assertViewHas('dadosTela', fn (string $json): bool => json_decode($json, true)['colunas']['triagem'][0]['proximidadeEnergia'] === 83);
        $this->getJson(route('vagas.show', $vaga))->assertOk()
            ->assertJsonPath('data.quadro-vaga.colunas.triagem.0.proximidadeEnergia', 83)
            ->assertJsonPath('data.quadro-vaga.colunas.triagem.0.nome', null);
    }

    #[TestWith([['dinamico_analitico' => 2], ['energia.individual_equipe', 'energia.execucao_planejamento']])]
    #[TestWith([['dinamico_analitico' => 2, 'individual_equipe' => 4], ['energia.execucao_planejamento']])]
    #[TestWith([['dinamico_analitico' => 6, 'individual_equipe' => 4, 'execucao_planejamento' => 2], ['energia.dinamico_analitico']])]
    #[TestWith(['inválida', ['energia']])]
    public function test_vaga_rejeita_energia_parcial_ou_invalida(mixed $energia, array $erros): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('vagas.store'), [...$this->dadosVaga(), 'energia' => $energia])
            ->assertUnprocessable()->assertJsonValidationErrors($erros);

        $this->assertDatabaseCount('vagas', 0);
    }

    public function test_vaga_salva_e_remove_os_tres_eixos_juntos(): void
    {
        $dados = $this->dadosVaga();
        $this->actingAs(User::factory()->create())->postJson(route('vagas.store'), [...$dados, 'energia' => $this->eixos()])->assertOk();
        $vaga = Vaga::query()->sole();
        $this->assertSame(2, (int) $vaga->energia_dinamico_analitico);
        $this->assertSame(4, (int) $vaga->energia_individual_equipe);
        $this->assertSame(2, (int) $vaga->energia_execucao_planejamento);

        $this->putJson(route('vagas.update', $vaga), [...$dados, 'energia' => array_fill_keys(array_keys($this->eixos()), '')])->assertOk();

        $this->assertFalse($vaga->fresh()->temPadraoEnergia());
        $this->assertNull($vaga->fresh()->energia_dinamico_analitico);
        $this->assertNull($vaga->fresh()->energia_individual_equipe);
        $this->assertNull($vaga->fresh()->energia_execucao_planejamento);
    }

    public function test_perfil_na_triagem_exibe_energia_limpa_sem_rotas_do_banco(): void
    {
        $candidato = Candidato::factory()->create(['nome' => 'Ágata Queiroz', 'email' => 'agata@exemplo.com.br']);
        $this->perfil($candidato, 'humano');
        $candidato->perfilEnergia()->update(['justificativa' => 'Ágata Queiroz trabalha em equipe; agata@exemplo.com.br']);
        $vaga = Vaga::factory()->create(['tipo_candidato' => 'ambos']);

        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $this->actingAs(User::factory()->create())->get(route('vagas.candidatos.show', [$vaga, $candidato->codigo]))
            ->assertOk()->assertSee('Padrão de energia')->assertSee('Revisado por humano')->assertSee('trabalha em equipe')
            ->assertDontSee($candidato->nome)->assertDontSee($candidato->email)
            ->assertDontSee(route('candidatos.energia.analisar', $candidato))->assertDontSee('Analisar com IA');
    }

    public function test_perfil_do_banco_sem_analise_oferece_ajuste_sem_js(): void
    {
        $candidato = Candidato::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('candidatos.show', $candidato))
            ->assertOk()->assertSee('Não analisado')->assertSee('Analisar com IA')->assertSee('Ajustar manualmente')
            ->assertSee('data-ajax', false)->assertSee('<noscript>', false)->assertDontSee($candidato->codigo);
    }

    public function test_triagem_em_outra_vaga_limpa_justificativa_manual_no_fragmento_json(): void
    {
        $usuario = User::factory()->create();
        $candidato = Candidato::factory()->create(['nome' => 'Ágata Queiroz', 'email' => 'agata@exemplo.com.br']);
        $vaga = Vaga::factory()->create(['tipo_candidato' => 'ambos']);
        $this->perfil($candidato, 'humano');
        $candidato->perfilEnergia()->update(['justificativa' => 'Ágata Queiroz trabalha em equipe; agata@exemplo.com.br']);
        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);
        $this->actingAs($usuario)->patchJson(route('vagas.candidatos.etapa', [$vaga, $candidato->codigo]), ['etapa' => 'entrevista'])->assertOk();
        $outraVaga = Vaga::factory()->create(['tipo_candidato' => 'ambos']);
        $outraVaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);

        $resposta = $this->patchJson(route('vagas.candidatos.etapa', [$outraVaga, $candidato->codigo]), ['etapa' => 'triagem', 'contexto' => 'perfil'])->assertOk();

        $html = $resposta->json('fragments.perfil-na-vaga');
        $this->assertStringContainsString('trabalha em equipe', $html);
        $this->assertStringNotContainsString('Ágata', $html);
        $this->assertStringNotContainsString('Queiroz', $html);
        $this->assertStringNotContainsString($candidato->email, $html);
        $this->assertStringNotContainsString(route('candidatos.show', $candidato), $html);
    }

    public function test_vaga_com_padrao_e_sem_perfil_mostra_nao_analisado(): void
    {
        Candidato::factory()->create();
        $vaga = Vaga::factory()->create(['tipo_candidato' => 'ambos', 'energia_dinamico_analitico' => 2, 'energia_individual_equipe' => 4, 'energia_execucao_planejamento' => 2]);

        $this->actingAs(User::factory()->create())->get(route('vagas.show', $vaga))->assertOk()->assertSee('Energia: Não analisado');
    }

    public function test_ajuste_sem_justificativa_e_permitido(): void
    {
        $candidato = Candidato::factory()->create();

        $this->actingAs(User::factory()->create())->putJson(route('candidatos.energia.update', $candidato), $this->eixos())->assertOk();

        $this->assertDatabaseHas('perfis_energia', ['candidato_id' => $candidato->id, 'origem' => 'humano', 'justificativa' => '']);
    }

    public function test_rotas_de_energia_exigem_login_e_recurso_existente(): void
    {
        $candidato = Candidato::factory()->create();
        $this->postJson(route('candidatos.energia.analisar', $candidato))->assertUnauthorized();
        $this->putJson(route('candidatos.energia.update', $candidato), $this->eixos())->assertUnauthorized();
        $this->post(route('candidatos.energia.analisar', $candidato))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->postJson('/candidatos/999999/energia')->assertNotFound();
        $this->assertDatabaseCount('perfis_energia', 0);
    }

    #[TestWith([false, 1])]
    #[TestWith([true, 2])]
    public function test_comando_processa_ativos_sem_perfil_e_todos_reanalisa_apenas_ia(bool $todos, int $quantidade): void
    {
        config(['banco_talentos.energia.habilitada' => true]);
        Candidato::factory()->create();
        $ia = Candidato::factory()->create();
        $humano = Candidato::factory()->create();
        Candidato::factory()->desativado()->create();
        $this->perfil($ia);
        $this->perfil($humano, 'humano');
        $falso = $this->analisador(new SugestaoEnergiaDTO(1, 2, 3, 'Nova análise'));

        $this->artisan('energia:analisar', ['--todos' => $todos])->expectsOutputToContain("{$quantidade} analisados, 0 indisponíveis")->assertSuccessful();

        $this->assertSame($quantidade, $falso->chamadas);
        $this->assertSame('Perfil preservado', $humano->perfilEnergia->justificativa);
        $this->assertDatabaseCount('perfis_energia', 3);
    }

    public function test_comando_desligado_avisa_sem_chamar_analisador(): void
    {
        $falso = $this->analisador(null);

        $this->artisan('energia:analisar')->expectsOutputToContain('Análise indisponível')->assertSuccessful();

        $this->assertSame(0, $falso->chamadas);
    }

    public function test_comando_conta_falhas_sem_gravar_perfil(): void
    {
        config(['banco_talentos.energia.habilitada' => true]);
        Candidato::factory()->create();
        $this->analisador(null);

        $this->artisan('energia:analisar')->expectsOutputToContain('0 analisados, 1 indisponíveis')->assertSuccessful();

        $this->assertDatabaseCount('perfis_energia', 0);
    }

    private function analisador(?SugestaoEnergiaDTO $sugestao): AnalisadorEnergia
    {
        $falso = new class($sugestao) implements AnalisadorEnergia
        {
            public string $recebido = '';

            public int $chamadas = 0;

            public function __construct(private ?SugestaoEnergiaDTO $sugestao) {}

            public function analisar(string $textoAnonimizado): ?SugestaoEnergiaDTO
            {
                $this->recebido = $textoAnonimizado;
                $this->chamadas++;

                return $this->sugestao;
            }
        };
        $this->app->instance(AnalisadorEnergia::class, $falso);

        return $falso;
    }

    private function perfil(Candidato $candidato, string $origem = 'ia'): void
    {
        $candidato->perfilEnergia()->create([
            'dinamico_analitico' => 3, 'individual_equipe' => 4, 'execucao_planejamento' => 1,
            'justificativa' => 'Perfil preservado', 'origem' => $origem, 'analisado_em' => now(),
        ]);
    }

    /** @return array{dinamico_analitico: int, individual_equipe: int, execucao_planejamento: int} */
    private function eixos(): array
    {
        return ['dinamico_analitico' => 2, 'individual_equipe' => 4, 'execucao_planejamento' => 2];
    }

    /** @return array{titulo: string, area: string, tipo_candidato: string, competencias: array<int, int>} */
    private function dadosVaga(): array
    {
        $competencia = Competencia::factory()->create();

        return ['titulo' => 'Vaga com energia', 'area' => 'dados', 'tipo_candidato' => 'ambos', 'competencias' => [$competencia->id => 3]];
    }
}
