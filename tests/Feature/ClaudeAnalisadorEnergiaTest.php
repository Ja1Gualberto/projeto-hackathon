<?php

namespace Tests\Feature;

use App\Services\Energia\ClaudeAnalisadorEnergia;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ClaudeAnalisadorEnergiaTest extends TestCase
{
    public function test_envia_contrato_messages_e_valida_sugestao_com_justificativa_truncada(): void
    {
        config(['services.anthropic.key' => 'chave-ficticia', 'services.anthropic.model' => 'modelo-teste']);
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/v1/messages' => Http::response($this->resposta([
            'dinamico_analitico' => 2, 'individual_equipe' => 4, 'execucao_planejamento' => 2,
            'justificativa' => str_repeat('á', 600),
        ]))]);

        $sugestao = $this->app->make(ClaudeAnalisadorEnergia::class)->analisar('Trabalhou com análise de dados.');

        $this->assertSame(2, $sugestao?->dinamicoAnalitico);
        $this->assertSame(4, $sugestao?->individualEquipe);
        $this->assertSame(2, $sugestao?->execucaoPlanejamento);
        $this->assertSame(str_repeat('á', 500), $sugestao?->justificativa);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'chave-ficticia')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && $request['model'] === 'modelo-teste' && $request['max_tokens'] === 2000
            && $request['output_config']['effort'] === 'low'
            && $request['output_config']['format']['type'] === 'json_schema'
            && $request['output_config']['format']['schema']['additionalProperties'] === false
            && $request['messages'][0]['content'] === 'Trabalhou com análise de dados.');
        Http::assertSentCount(1);
    }

    #[TestWith([400])]
    #[TestWith([429])]
    #[TestWith([500])]
    #[TestWith([529])]
    public function test_erro_http_retorna_null_sem_repeticao_ou_dados_sensiveis_no_log(int $status): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/v1/messages' => Http::response(['error' => 'CONTEÚDO SENSÍVEL'], $status)]);
        Log::spy();

        $this->assertNull($this->app->make(ClaudeAnalisadorEnergia::class)->analisar('CURRÍCULO PRIVADO'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Análise de energia indisponível.', ['motivo' => 'http', 'status' => $status]);
    }

    public function test_conexao_falha_retorna_null_sem_expor_excecao(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/v1/messages' => Http::failedConnection('SEGREDO')]);
        Log::spy();

        $this->assertNull($this->app->make(ClaudeAnalisadorEnergia::class)->analisar('CURRÍCULO PRIVADO'));

        Log::shouldHaveReceived('warning')->once()->with('Análise de energia indisponível.', ['motivo' => 'conexao']);
    }

    #[TestWith(['refusal'])]
    #[TestWith(['max_tokens'])]
    public function test_recusa_ou_resposta_incompleta_nao_gera_perfil(string $motivo): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/v1/messages' => Http::response(['stop_reason' => $motivo, 'content' => [['type' => 'text', 'text' => '{}']]])]);

        $this->assertNull($this->app->make(ClaudeAnalisadorEnergia::class)->analisar('Texto anonimizado'));

        Http::assertSentCount(1);
    }

    #[TestWith(['não é JSON'])]
    #[TestWith(['{"dinamico_analitico":6,"individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste"}'])]
    #[TestWith(['{"dinamico_analitico":"2","individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste"}'])]
    #[TestWith(['{"dinamico_analitico":2,"individual_equipe":4,"execucao_planejamento":2,"justificativa":null}'])]
    #[TestWith(['{"dinamico_analitico":2,"individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste","extra":1}'])]
    #[TestWith(['{}'])]
    public function test_json_fora_do_esquema_retorna_null(string $texto): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.anthropic.com/v1/messages' => Http::response(['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => $texto]]])]);

        $this->assertNull($this->app->make(ClaudeAnalisadorEnergia::class)->analisar('Texto anonimizado'));

        Http::assertSentCount(1);
    }

    /**
     * @param  array{dinamico_analitico: int, individual_equipe: int, execucao_planejamento: int, justificativa: string}  $dados
     * @return array{stop_reason: string, content: list<array{type: string, text: string}>}
     */
    private function resposta(array $dados): array
    {
        return ['stop_reason' => 'end_turn', 'content' => [['type' => 'text', 'text' => json_encode($dados)]]];
    }
}
