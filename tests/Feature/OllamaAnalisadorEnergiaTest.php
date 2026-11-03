<?php

namespace Tests\Feature;

use App\Services\Energia\OllamaAnalisadorEnergia;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class OllamaAnalisadorEnergiaTest extends TestCase
{
    public function test_envia_texto_ao_ollama_sem_chave_com_schema_e_valida_sugestao(): void
    {
        config(['services.ollama.url' => 'http://ollama-teste:11434/', 'services.ollama.model' => 'llama-teste']);
        Http::preventStrayRequests();
        Http::fake(['ollama-teste:11434/api/chat' => Http::response($this->resposta(json_encode([
            'dinamico_analitico' => 2, 'individual_equipe' => 4, 'execucao_planejamento' => 2,
            'justificativa' => str_repeat('á', 600),
        ])))]);

        $sugestao = $this->app->make(OllamaAnalisadorEnergia::class)->analisar('Trabalhou com análise de dados.');

        $this->assertSame(2, $sugestao?->dinamicoAnalitico);
        $this->assertSame(4, $sugestao?->individualEquipe);
        $this->assertSame(2, $sugestao?->execucaoPlanejamento);
        $this->assertSame(str_repeat('á', 500), $sugestao?->justificativa);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://ollama-teste:11434/api/chat'
            && $request->method() === 'POST'
            && ! $request->hasHeader('Authorization') && ! $request->hasHeader('x-api-key')
            && $request['model'] === 'llama-teste' && $request['stream'] === false
            && $request['messages'][0]['role'] === 'system'
            && $request['messages'][1] === ['role' => 'user', 'content' => 'Trabalhou com análise de dados.']
            && $request['format']['type'] === 'object'
            && $request['format']['properties']['dinamico_analitico'] === ['type' => 'integer', 'enum' => [1, 2, 3, 4, 5]]
            && $request['format']['required'] === ['dinamico_analitico', 'individual_equipe', 'execucao_planejamento', 'justificativa']
            && $request['format']['additionalProperties'] === false
            && $request['options'] === ['temperature' => 0, 'num_predict' => 1024]);
        Http::assertSentCount(1);
    }

    #[TestWith([301])]
    #[TestWith([400])]
    #[TestWith([404])]
    #[TestWith([500])]
    #[TestWith([503])]
    public function test_erro_http_retorna_null_sem_repeticao_ou_dados_sensiveis_no_log(int $status): void
    {
        Http::preventStrayRequests();
        Http::fake(['ollama:11434/api/chat' => Http::response(['error' => 'CONTEÚDO SENSÍVEL'], $status)]);
        Log::spy();

        $this->assertNull($this->app->make(OllamaAnalisadorEnergia::class)->analisar('CURRÍCULO PRIVADO'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Análise de energia indisponível.', ['provedor' => 'ollama', 'motivo' => 'http', 'status' => $status]);
    }

    public function test_conexao_falha_retorna_null_sem_expor_excecao(): void
    {
        Http::preventStrayRequests();
        Http::fake(['ollama:11434/api/chat' => Http::failedConnection('SEGREDO')]);
        Log::spy();

        $this->assertNull($this->app->make(OllamaAnalisadorEnergia::class)->analisar('CURRÍCULO PRIVADO'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Análise de energia indisponível.', ['provedor' => 'ollama', 'motivo' => 'conexao']);
    }

    #[TestWith([false, 'stop'])]
    #[TestWith([true, 'length'])]
    #[TestWith([true, null])]
    public function test_resposta_incompleta_retorna_null(bool $concluida, ?string $motivo): void
    {
        $resposta = $this->resposta('{"dinamico_analitico":2,"individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste"}');
        $resposta['done'] = $concluida;
        $resposta['done_reason'] = $motivo;
        Http::preventStrayRequests();
        Http::fake(['ollama:11434/api/chat' => Http::response($resposta)]);

        $this->assertNull($this->app->make(OllamaAnalisadorEnergia::class)->analisar('Texto anonimizado'));

        Http::assertSentCount(1);
    }

    #[TestWith(['não é JSON'])]
    #[TestWith(['{"dinamico_analitico":6,"individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste"}'])]
    #[TestWith(['{"dinamico_analitico":0,"individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste"}'])]
    #[TestWith(['{"dinamico_analitico":"2","individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste"}'])]
    #[TestWith(['{"dinamico_analitico":2,"individual_equipe":4,"execucao_planejamento":2,"justificativa":null}'])]
    #[TestWith(['{"dinamico_analitico":2,"individual_equipe":4,"execucao_planejamento":2,"justificativa":"teste","extra":1}'])]
    #[TestWith(['{"dinamico_analitico":2,"individual_equipe":4,"outro":2,"justificativa":"teste"}'])]
    #[TestWith(['{}'])]
    public function test_json_fora_do_esquema_retorna_null(string $texto): void
    {
        Http::preventStrayRequests();
        Http::fake(['ollama:11434/api/chat' => Http::response($this->resposta($texto))]);
        Log::spy();

        $this->assertNull($this->app->make(OllamaAnalisadorEnergia::class)->analisar('Texto anonimizado'));

        Http::assertSentCount(1);
        Log::shouldHaveReceived('warning')->once()->with('Análise de energia indisponível.', ['provedor' => 'ollama', 'motivo' => 'resposta_invalida']);
    }

    #[TestWith([null])]
    #[TestWith([42])]
    public function test_conteudo_ausente_ou_nao_textual_retorna_null(mixed $texto): void
    {
        Http::preventStrayRequests();
        Http::fake(['ollama:11434/api/chat' => Http::response([
            'done' => true, 'done_reason' => 'stop', 'message' => ['content' => $texto],
        ])]);

        $this->assertNull($this->app->make(OllamaAnalisadorEnergia::class)->analisar('Texto anonimizado'));

        Http::assertSentCount(1);
    }

    /** @return array{done: bool, done_reason: string, message: array{role: string, content: string}} */
    private function resposta(string $texto): array
    {
        return ['done' => true, 'done_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $texto]];
    }
}
