<?php

namespace Tests\Feature;

use App\Services\Energia\OpenAiAnalisadorEnergia;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OpenAiAnalisadorEnergiaTest extends TestCase
{
    public function test_envia_responses_com_schema_sem_armazenamento_e_valida_sugestao(): void
    {
        config(['services.openai.key' => 'chave-ficticia', 'services.openai.model' => 'modelo-teste']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->resposta(json_encode([
            'dinamico_analitico' => 2, 'individual_equipe' => 4, 'execucao_planejamento' => 2,
            'justificativa' => str_repeat('á', 600),
        ])))]);

        $sugestao = $this->app->make(OpenAiAnalisadorEnergia::class)->analisar('Trabalhou com análise de dados.');

        $this->assertSame(2, $sugestao?->dinamicoAnalitico);
        $this->assertSame(4, $sugestao?->individualEquipe);
        $this->assertSame(2, $sugestao?->execucaoPlanejamento);
        $this->assertSame(str_repeat('á', 500), $sugestao?->justificativa);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer chave-ficticia')
            && $request['model'] === 'modelo-teste' && $request['max_output_tokens'] === 2000
            && $request['store'] === false
            && $request['text']['format']['type'] === 'json_schema'
            && $request['text']['format']['strict'] === true
            && $request['text']['format']['schema']['additionalProperties'] === false
            && $request['input'][0] === ['role' => 'user', 'content' => 'Trabalhou com análise de dados.']);
        Http::assertSentCount(1);
    }

    #[TestWith([400])]
    #[TestWith([401])]
    #[TestWith([403])]
    #[TestWith([404])]
    #[TestWith([429])]
    #[TestWith([500])]
    public function test_erro_http_retorna_null_sem_repeticao_ou_dados_sensiveis_no_log(int $status): void
    {
        config(['services.openai.key' => 'chave-ficticia']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/responses' => Http::response(['error' => 'CONTEÚDO SENSÍVEL'], $status)]);
        Log::spy();

        $this->assertNull($this->app->make(OpenAiAnalisadorEnergia::class)->analisar('CURRÍCULO PRIVADO'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Análise de energia indisponível.', ['provedor' => 'openai', 'motivo' => 'http', 'status' => $status]);
    }

    public function test_conexao_falha_retorna_null_sem_expor_excecao(): void
    {
        config(['services.openai.key' => 'chave-ficticia']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/responses' => Http::failedConnection('SEGREDO')]);
        Log::spy();

        $this->assertNull($this->app->make(OpenAiAnalisadorEnergia::class)->analisar('CURRÍCULO PRIVADO'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Análise de energia indisponível.', ['provedor' => 'openai', 'motivo' => 'conexao']);
    }

    #[TestWith(['incomplete'])]
    #[TestWith(['failed'])]
    public function test_resposta_nao_concluida_retorna_null(string $status): void
    {
        config(['services.openai.key' => 'chave-ficticia']);
        $resposta = $this->resposta('{"dinamico_analitico":2,"individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste"}');
        $resposta['status'] = $status;
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/responses' => Http::response($resposta)]);

        $this->assertNull($this->app->make(OpenAiAnalisadorEnergia::class)->analisar('Texto anonimizado'));

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
        config(['services.openai.key' => 'chave-ficticia']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/responses' => Http::response($this->resposta($texto))]);

        $this->assertNull($this->app->make(OpenAiAnalisadorEnergia::class)->analisar('Texto anonimizado'));

        Http::assertSentCount(1);
    }

    /** @param array{status: string, output: list<array<string, mixed>>|null} $resposta */
    #[TestWith([['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'refusal', 'refusal' => 'Não posso responder.']]]]]])]
    #[TestWith([['status' => 'completed', 'output' => null]])]
    #[TestWith([['status' => 'completed', 'output' => [['type' => 'message', 'content' => null]]]])]
    #[TestWith([['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => 42]]]]]])]
    public function test_recusa_ou_estrutura_invalida_retorna_null(array $resposta): void
    {
        config(['services.openai.key' => 'chave-ficticia']);
        Http::preventStrayRequests();
        Http::fake(['api.openai.com/v1/responses' => Http::response($resposta)]);

        $this->assertNull($this->app->make(OpenAiAnalisadorEnergia::class)->analisar('Texto anonimizado'));

        Http::assertSentCount(1);
    }

    /** @return array{status: string, output: list<array<string, mixed>>} */
    private function resposta(string $texto): array
    {
        return [
            'status' => 'completed',
            'output' => [
                ['type' => 'reasoning', 'summary' => []],
                ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => $texto]]],
            ],
        ];
    }
}
