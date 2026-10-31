<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelTest extends TestCase
{
    use RefreshDatabase;

    public function test_painel_conta_apenas_ativos_e_vagas_abertas_e_ordena_competencias(): void
    {
        $servidores = Candidato::factory()->servidor()->count(2)->create();
        $estagiario = Candidato::factory()->estagiario()->create();
        $desativado = Candidato::factory()->desativado()->create();
        Vaga::factory()->count(2)->create();
        Vaga::factory()->encerrada()->create();
        $python = Competencia::factory()->hard()->create(['nome' => 'Python']);
        $sql = Competencia::factory()->hard()->create(['nome' => 'SQL']);
        $oculta = Competencia::factory()->hard()->create(['nome' => 'Somente desativado']);
        foreach ($servidores as $servidor) {
            $servidor->competencias()->attach($python, ['origem' => 'curriculo']);
        }
        $estagiario->competencias()->attach($sql, ['origem' => 'curriculo']);
        $desativado->competencias()->attach($oculta, ['origem' => 'curriculo']);

        $resposta = $this->actingAs(User::factory()->create())->get(route('painel'));

        $resposta->assertOk()->assertSee('Talentos')->assertSee('Servidores')->assertSee('Estagiários')
            ->assertSee('Vagas abertas')->assertSeeInOrder(['Python', 'SQL'])->assertDontSee($oculta->nome);
        $this->assertSame(3, $resposta['totalTalentos']);
        $this->assertSame(2, $resposta['servidores']);
        $this->assertSame(1, $resposta['estagiarios']);
        $this->assertSame(2, $resposta['vagasAbertas']);
        $this->assertSame([2, 1], $resposta['topCompetencias']->pluck('total')->map(fn ($total): int => (int) $total)->all());
    }

    public function test_painel_vazio_orienta_o_primeiro_cadastro(): void
    {
        $this->actingAs(User::factory()->create())->get(route('painel'))
            ->assertOk()->assertSee('Nenhuma competência cadastrada nos perfis')
            ->assertViewHas('totalTalentos', 0)->assertViewHas('vagasAbertas', 0);
    }
}
