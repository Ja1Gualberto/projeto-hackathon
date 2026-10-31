<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutenticacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitante_e_redirecionado_para_o_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_tela_de_login_e_exibida_para_visitante(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Entrar')
            ->assertSee('Ambiente de demonstração — dados fictícios.');
    }

    public function test_login_valido_redireciona_para_o_painel(): void
    {
        $usuario = User::factory()->create(['password' => 'senha-segura']);

        $this->post(route('login.store'), [
            'email' => $usuario->email,
            'password' => 'senha-segura',
        ])->assertRedirect(route('painel'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_login_invalido_volta_com_erro_e_continua_visitante(): void
    {
        $usuario = User::factory()->create(['password' => 'senha-segura']);

        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => $usuario->email,
                'password' => 'senha-errada',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'E-mail ou senha inválidos.']);

        $this->assertGuest();
    }

    public function test_login_exige_email_e_senha(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [])
            ->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_logout_encerra_a_sessao(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_usuario_autenticado_ve_o_layout_com_a_navegacao(): void
    {
        $usuario = User::factory()->create(['name' => 'Recrutadora Teste']);

        $this->actingAs($usuario)
            ->get(route('painel'))
            ->assertOk()
            ->assertSee('Recrutadora Teste')
            ->assertSee('Candidatos')
            ->assertSee('Importar currículo')
            ->assertSee('Vagas')
            ->assertSee('Sair');
    }

    public function test_usuario_autenticado_nao_volta_para_o_login(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect(route('painel'));
    }
}
