<?php

namespace Tests\Feature;

use App\DTOs\SugestaoEnergiaDTO;
use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use App\Services\Energia\AnalisadorEnergia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ParticipacaoVisualizacaoTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['ia', false, 'Sugerido pela IA'])]
    #[TestWith(['humano', true, 'Revisado por humano'])]
    public function test_visualizacao_exibe_pdf_analise_salva_e_aderencia_sem_reanalisar(string $origem, bool $encerrada, string $rotulo): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = $encerrada ? Vaga::factory()->encerrada()->create() : Vaga::factory()->create();
        $vaga->update(['energia_dinamico_analitico' => 3, 'energia_individual_equipe' => 4, 'energia_execucao_planejamento' => 1]);
        $candidato = $this->candidato($vaga, pdf: true, analise: true, origem: $origem);
        $this->mock(AnalisadorEnergia::class)->shouldNotReceive('analisar');

        $resposta = $this->getJson(route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]));

        $resposta->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('redirect', null)->assertJsonPath('message', null);
        $this->assertSame(['visualizacao-candidato'], array_keys($resposta->json('fragments')));
        $html = $resposta->json('fragments.visualizacao-candidato');
        $this->assertStringContainsString('<iframe', $html);
        $this->assertStringContainsString(route('vagas.candidatos.curriculo', [$vaga, $candidato->codigo]), $html);
        $this->assertStringContainsString('Abrir PDF em nova aba', $html);
        $this->assertStringContainsString($rotulo, $html);
        $this->assertStringContainsString('Evidências de colaboração e planejamento.', $html);
        $this->assertStringContainsString('100% de proximidade com a vaga', $html);
        $this->assertStringContainsString('Aderência a esta vaga', $html);
        $this->assertStringContainsString('Gerar novamente', $html);
        $this->assertDatabaseHas('perfis_energia', ['candidato_id' => $candidato->id, 'origem' => $origem]);
    }

    #[TestWith([false, true])]
    #[TestWith([true, false])]
    #[TestWith([false, false])]
    public function test_recursos_ausentes_tem_avisos_independentes(bool $pdf, bool $analise): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga, pdf: $pdf, analise: $analise);

        $resposta = $this->getJson(route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]));

        $resposta->assertOk();
        $html = $resposta->json('fragments.visualizacao-candidato');
        $this->assertSame(! $pdf, str_contains($html, 'Nenhum PDF disponível para este candidato.'));
        $this->assertSame($pdf, str_contains($html, '<iframe'));
        $this->assertSame(! $analise, str_contains($html, 'Nenhuma análise de IA disponível para este candidato.'));
        $this->assertSame($analise, str_contains($html, 'Evidências de colaboração e planejamento.'));
    }

    public function test_visualizacao_em_triagem_responde_403_mesmo_com_entrevista_em_outra_vaga(): void
    {
        $this->actingAs(User::factory()->create());
        $outraVaga = Vaga::factory()->create();
        $candidato = $this->candidato($outraVaga, pdf: true, analise: true);
        $vaga = Vaga::factory()->create();
        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'triagem']);

        $this->getJson(route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]))
            ->assertForbidden()->assertJsonMissingPath('fragments');
    }

    public function test_candidato_sem_vinculo_com_a_vaga_responde_404(): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = Candidato::factory()->create();

        $this->getJson(route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]))->assertNotFound();
    }

    public function test_visitante_nao_pode_acessar_a_visualizacao(): void
    {
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga);

        $this->getJson(route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]))->assertUnauthorized();
    }

    public function test_nome_e_justificativa_sao_escapados_no_html(): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga, pdf: true, analise: true);
        $candidato->update(['nome' => '<img src=x onerror=alert(1)>']);
        $candidato->perfilEnergia()->update(['justificativa' => '<script>alert(2)</script>']);

        $resposta = $this->getJson(route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]));

        $resposta->assertOk();
        $html = $resposta->json('fragments.visualizacao-candidato');
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    #[TestWith(['triagem'])]
    #[TestWith(['entrevista'])]
    #[TestWith(['aprovado'])]
    #[TestWith(['reprovado'])]
    public function test_quadro_oferece_visualizacao_somente_na_entrevista(string $etapa): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga, etapa: $etapa);

        $this->getJson(route('vagas.show', $vaga))->assertOk()->assertJsonPath(
            'data.quadro-vaga.colunas.'.$etapa.'.0.visualizacaoUrl',
            $etapa === 'entrevista' ? route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]) : null,
        );
    }

    public function test_botao_de_gerar_analise_aponta_para_a_vaga_e_candidato_da_modal(): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga);

        $resposta = $this->getJson(route('vagas.candidatos.visualizacao', [$vaga, $candidato->codigo]));

        $resposta->assertOk();
        $html = $resposta->json('fragments.visualizacao-candidato');
        $this->assertStringContainsString('Gerar análise de IA', $html);
        $this->assertStringContainsString(route('vagas.candidatos.energia.analisar', [$vaga, $candidato->codigo]), $html);
        $this->assertStringContainsString('name="_token"', $html);
    }

    public function test_gerar_analise_salva_perfil_e_atualiza_apenas_a_secao_de_ia_da_modal(): void
    {
        $this->freezeTime();
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga);
        $this->mock(AnalisadorEnergia::class)->shouldReceive('analisar')->once()
            ->andReturn(new SugestaoEnergiaDTO(2, 5, 4, 'Experiência com trabalho em equipe.'));

        $resposta = $this->postJson(route('vagas.candidatos.energia.analisar', [$vaga, $candidato->codigo]));

        $resposta->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('redirect', null);
        $this->assertSame(['visualizacao-energia'], array_keys($resposta->json('fragments')));
        $html = $resposta->json('fragments.visualizacao-energia');
        $this->assertStringContainsString('Experiência com trabalho em equipe.', $html);
        $this->assertStringContainsString('Sugerido pela IA', $html);
        $this->assertStringContainsString('Gerar novamente', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertDatabaseHas('perfis_energia', [
            'candidato_id' => $candidato->id, 'origem' => 'ia',
            'dinamico_analitico' => 2, 'individual_equipe' => 5, 'execucao_planejamento' => 4,
            'justificativa' => 'Experiência com trabalho em equipe.', 'analisado_em' => now()->toDateTimeString(),
        ]);
    }

    public function test_ia_indisponivel_retorna_503_e_preserva_analise_anterior(): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga, analise: true, origem: 'humano');
        $this->mock(AnalisadorEnergia::class)->shouldReceive('analisar')->once()->andReturnNull();

        $this->postJson(route('vagas.candidatos.energia.analisar', [$vaga, $candidato->codigo]))
            ->assertServiceUnavailable()->assertExactJson(['message' => 'Análise indisponível no momento.']);

        $this->assertDatabaseHas('perfis_energia', [
            'candidato_id' => $candidato->id, 'origem' => 'humano',
            'justificativa' => 'Evidências de colaboração e planejamento.',
        ]);
        $this->assertDatabaseCount('perfis_energia', 1);
    }

    public function test_gerar_analise_em_triagem_responde_403_sem_chamar_ia(): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga, etapa: 'triagem');
        $outraVaga = Vaga::factory()->create();
        $outraVaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => 'entrevista']);
        $this->mock(AnalisadorEnergia::class)->shouldNotReceive('analisar');

        $this->postJson(route('vagas.candidatos.energia.analisar', [$vaga, $candidato->codigo]))->assertForbidden();

        $this->assertDatabaseCount('perfis_energia', 0);
    }

    public function test_geracao_sem_vinculo_responde_404_sem_chamar_ia(): void
    {
        $this->actingAs(User::factory()->create());
        $vaga = Vaga::factory()->create();
        $candidato = Candidato::factory()->create();
        $this->mock(AnalisadorEnergia::class)->shouldNotReceive('analisar');

        $this->postJson(route('vagas.candidatos.energia.analisar', [$vaga, $candidato->codigo]))->assertNotFound();

        $this->assertDatabaseCount('perfis_energia', 0);
    }

    public function test_visitante_nao_pode_gerar_analise(): void
    {
        $vaga = Vaga::factory()->create();
        $candidato = $this->candidato($vaga);
        $this->mock(AnalisadorEnergia::class)->shouldNotReceive('analisar');

        $this->postJson(route('vagas.candidatos.energia.analisar', [$vaga, $candidato->codigo]))->assertUnauthorized();

        $this->assertDatabaseCount('perfis_energia', 0);
    }

    private function candidato(Vaga $vaga, bool $pdf = false, bool $analise = false, string $origem = 'ia', string $etapa = 'entrevista'): Candidato
    {
        Storage::fake('curriculos');
        if ($pdf) {
            Storage::disk('curriculos')->put('curriculo.pdf', '%PDF-1.4 currículo de teste');
        }
        $candidato = Candidato::factory()->create([
            'nome' => 'Zélia Quintanilha',
            'curriculo_arquivo' => $pdf ? 'curriculo.pdf' : null,
            'curriculo_nome_original' => $pdf ? 'curriculo.pdf' : null,
        ]);
        $vaga->participacoes()->create(['candidato_id' => $candidato->id, 'etapa' => $etapa]);
        if ($analise) {
            $candidato->perfilEnergia()->create([
                'dinamico_analitico' => 3, 'individual_equipe' => 4, 'execucao_planejamento' => 1,
                'justificativa' => 'Evidências de colaboração e planejamento.', 'origem' => $origem,
                'analisado_em' => '2026-11-03 10:00:00',
            ]);
        }

        return $candidato;
    }
}
