<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AlertasTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['sucesso', 'success.svg', 'status', 4500])]
    #[TestWith(['erro', 'fail.svg', 'alert', 4500])]
    #[TestWith(['aviso', 'warning.svg', 'alert', 4500])]
    #[TestWith(['info', 'help.svg', 'status', 4500])]
    public function test_alerta_usa_a_arte_do_tipo_e_permite_fechamento(string $tipo, string $arquivo, string $papel, int $duracao): void
    {
        $view = $this->blade('<x-alerta :tipo="$tipo">Mensagem da operação.</x-alerta>', compact('tipo'));

        $view->assertSee('images/message/'.$arquivo, false)
            ->assertSee('role="'.$papel.'"', false)
            ->assertSee('data-alerta-duracao="'.$duracao.'"', false)
            ->assertSee('aria-label="Fechar alerta"', false)
            ->assertSee('Mensagem da operação.');
    }

    public function test_tipo_desconhecido_usa_alerta_informativo(): void
    {
        $view = $this->blade('<x-alerta tipo="desconhecido">Mensagem.</x-alerta>');

        $view->assertSee('data-alerta-tipo="info"', false)
            ->assertSee('images/message/help.svg', false);
    }

    public function test_alerta_escapa_titulo_e_mensagem(): void
    {
        $conteudo = '<script>alert("teste")</script>';

        $view = $this->blade('<x-alerta :titulo="$conteudo">{{ $conteudo }}</x-alerta>', compact('conteudo'));

        $view->assertSee($conteudo)
            ->assertDontSee($conteudo, false);
    }

    public function test_mensagem_de_sessao_usa_alerta_e_anuncios_ajax_nao_ocupam_espaco_na_pagina(): void
    {
        $usuario = User::factory()->create();

        $resposta = $this->actingAs($usuario)
            ->withSession(['status' => 'Renata Barros está agora em Entrevista.'])
            ->get(route('painel'));

        $resposta->assertOk()
            ->assertSee('data-alertas', false)
            ->assertSee('Renata Barros está agora em Entrevista.')
            ->assertSee('data-ajax-anuncios role="status" aria-live="polite" aria-atomic="true" class="sr-only"', false);
    }

    public function test_falha_no_login_exibe_alerta_sem_remover_erros_dos_campos(): void
    {
        $view = $this->withViewErrors(['email' => 'E-mail ou senha inválidos.'])->view('auth.login');

        $view->assertSee('Não foi possível entrar')
            ->assertSee('E-mail ou senha inválidos.')
            ->assertSee('aria-invalid="true"', false);
    }
}
