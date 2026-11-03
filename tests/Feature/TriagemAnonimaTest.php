<?php

namespace Tests\Feature;

use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Triagem anônima por vaga (US4): invariantes 1, 3, 4, 6, 9 e 10 de
 * contracts/visibilidade-por-contexto.md, nas páginas e nos envelopes JSON (T096, T159).
 */
class TriagemAnonimaTest extends TestCase
{
    use RefreshDatabase;

    private const NOME = 'Fernanda Quintela';

    private const EMAIL = 'fernanda.quintela@exemplo.com.br';

    private const TELEFONE = '(69) 98888-7777';

    private const CPF = '52998224725';

    private Candidato $candidato;

    private Vaga $vagaA;

    private Vaga $vagaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['name' => 'Recrutadora Teste']));
        Storage::fake('curriculos');
        Storage::disk('curriculos')->put('fernanda.pdf', '%PDF-1.4 fictício');

        $this->candidato = $this->candidato(['Python', 'SQL'], [
            'curriculo_arquivo' => 'fernanda.pdf',
            'curriculo_nome_original' => 'curriculo-fernanda.pdf',
            'nome' => self::NOME,
            'email' => self::EMAIL,
            'telefone' => self::TELEFONE,
            'cpf' => self::CPF,
            'experiencias' => ['Estágio na Prefeitura (fictícia) — contato fernanda.q@prefeitura.exemplo e https://fernanda.dev/portfolio, tel. (69) 3222-1234'],
            'formacoes' => ['Sistemas de Informação — projeto de Fernanda Quintela sobre dados abertos'],
            'links' => ['https://github.com/fernanda-demo', 'https://linkedin.com/in/fernanda-demo'],
        ]);
        $this->vagaA = $this->vaga(['Python' => 3]);
        $this->vagaB = $this->vaga(['SQL' => 3]);
    }

    private function competencia(string $nome): Competencia
    {
        return Competencia::query()->where('nome', $nome)->first()
            ?? Competencia::factory()->hard()->create(['nome' => $nome]);
    }

    /** @param array<string, int> $pesos */
    private function vaga(array $pesos, string $tipo = 'estagiario'): Vaga
    {
        $vaga = Vaga::factory()->create(['tipo_candidato' => $tipo]);
        $vaga->competencias()->attach(collect($pesos)
            ->mapWithKeys(fn (int $peso, string $nome): array => [$this->competencia($nome)->id => ['peso' => $peso]])
            ->all());

        return $vaga;
    }

    /** @param list<string> $competencias */
    private function candidato(array $competencias, array $atributos = []): Candidato
    {
        $candidato = Candidato::factory()->estagiario()->create($atributos);
        $candidato->competencias()->attach(
            collect($competencias)->map(fn (string $nome): int => $this->competencia($nome)->id)->all(),
            ['origem' => 'curriculo'],
        );

        return $candidato;
    }

    private function mover(Vaga $vaga, Candidato $candidato, string $etapa, string $contexto = 'quadro', bool $json = false): TestResponse
    {
        $rota = route('vagas.candidatos.etapa', [$vaga, $candidato->codigo]);
        $dados = ['etapa' => $etapa, 'contexto' => $contexto];

        return $json ? $this->patchJson($rota, $dados) : $this->patch($rota, $dados);
    }

    private function perfil(Vaga $vaga, ?Candidato $candidato = null): TestResponse
    {
        return $this->get(route('vagas.candidatos.show', [$vaga, ($candidato ?? $this->candidato)->codigo]));
    }

    /** @param list<string> $conteudos */
    private function assertSemIdentidade(array $conteudos, ?Candidato $candidato = null): void
    {
        $candidato ??= $this->candidato;

        foreach ($conteudos as $conteudo) {
            foreach ([$candidato->nome, $candidato->email, $candidato->telefone, $candidato->cpf, route('candidatos.show', $candidato)] as $proibido) {
                if ($proibido !== null) {
                    $this->assertStringNotContainsString($proibido, $conteudo);
                }
            }
        }
    }

    public function test_em_triagem_quadro_e_perfil_nao_contem_identidade_nem_link_do_banco(): void
    {
        $quadro = $this->get(route('vagas.show', $this->vagaA))->assertOk()->assertSee($this->candidato->codigo);
        $perfil = $this->perfil($this->vagaA)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee($this->candidato->codigo)
            ->assertSee('Identidade protegida na triagem anônima')
            ->assertSee('2 links — visíveis a partir da entrevista')
            ->assertDontSee('Abrir PDF');

        $this->assertSemIdentidade([$quadro->getContent(), $perfil->getContent()]);
        $this->assertStringNotContainsString('github.com/fernanda-demo', $perfil->getContent());
    }

    public function test_textos_livres_da_triagem_saem_sem_contatos_urls_e_nome(): void
    {
        $perfil = $this->perfil($this->vagaA)->assertOk()->getContent();

        $this->assertStringContainsString('Estágio na Prefeitura (fictícia)', $perfil);
        $this->assertStringContainsString('[oculto]', $perfil);
        foreach (['fernanda.q@prefeitura.exemplo', 'https://fernanda.dev/portfolio', '(69) 3222-1234', 'Fernanda', 'Quintela'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $perfil);
        }

        $this->mover($this->vagaA, $this->candidato, 'entrevista');

        $this->perfil($this->vagaA)
            ->assertSee('https://fernanda.dev/portfolio')
            ->assertSee('projeto de Fernanda Quintela');
    }

    public function test_entrevista_revela_a_identidade_so_na_propria_vaga(): void
    {
        $this->mover($this->vagaA, $this->candidato, 'entrevista')->assertRedirect(route('vagas.show', $this->vagaA));

        $this->get(route('vagas.show', $this->vagaA))->assertSee(self::NOME)->assertSee($this->candidato->codigo);
        $this->perfil($this->vagaA)
            ->assertSee(self::NOME)
            ->assertSee(self::EMAIL)
            ->assertSee(self::TELEFONE)
            ->assertSee('https://github.com/fernanda-demo')
            ->assertSee('Abrir PDF')
            ->assertDontSee('Identidade protegida na triagem anônima');

        $quadroB = $this->get(route('vagas.show', $this->vagaB))->assertOk()->assertSee($this->candidato->codigo)->getContent();
        $perfilB = $this->perfil($this->vagaB)->assertOk()->assertSee('Identidade protegida na triagem anônima')->getContent();
        $this->assertSemIdentidade([$quadroB, $perfilB]);
    }

    public function test_aprovado_mantem_identidade_e_nao_pode_voltar_para_triagem(): void
    {
        $this->mover($this->vagaA, $this->candidato, 'entrevista');
        $this->mover($this->vagaA, $this->candidato, 'aprovado');
        $this->get(route('vagas.show', $this->vagaA))->assertSee(self::NOME);

        $this->mover($this->vagaA, $this->candidato, 'triagem')
            ->assertSessionHasErrors(['etapa' => 'Não é possível voltar à triagem após iniciar a entrevista.']);

        $this->perfil($this->vagaA)->assertSee(self::NOME)->assertSee('Etapa nesta vaga: Aprovado');
        $this->assertSemIdentidade([$this->perfil($this->vagaB)->getContent()]);
    }

    public function test_anotacoes_so_aparecem_no_perfil_revelado(): void
    {
        Anotacao::factory()->create([
            'candidato_id' => $this->candidato->id,
            'vaga_id' => $this->vagaB->id,
            'texto' => 'Explicou com segurança o projeto de dados abertos.',
        ]);

        $this->perfil($this->vagaA)->assertDontSee('Explicou com segurança o projeto de dados abertos.');

        $this->mover($this->vagaA, $this->candidato, 'entrevista');

        $this->perfil($this->vagaA)
            ->assertSee('Anotações de entrevista')
            ->assertSee('Explicou com segurança o projeto de dados abertos.')
            ->assertSee($this->vagaB->titulo);
    }

    public function test_gestao_do_banco_nunca_mostra_o_codigo_da_triagem(): void
    {
        $this->get(route('candidatos.index'))->assertOk()->assertSee(self::NOME)->assertDontSee($this->candidato->codigo);
        $this->get(route('candidatos.show', $this->candidato))->assertOk()->assertDontSee($this->candidato->codigo);
    }

    public function test_perfil_de_quem_nao_participa_do_quadro_responde_404(): void
    {
        $servidor = Candidato::factory()->servidor()->create();
        $desativado = Candidato::factory()->estagiario()->desativado()->create();

        $this->perfil($this->vagaA, $servidor)->assertNotFound();
        $this->perfil($this->vagaA, $desativado)->assertNotFound();
        $this->get('/vagas/'.$this->vagaA->id.'/candidatos/CAND-ABCD')->assertNotFound();
    }

    public function test_json_do_quadro_e_do_perfil_respeitam_o_anonimato_por_vaga(): void
    {
        $revelado = $this->candidato(['Python'], ['nome' => 'Otávio Brandão', 'email' => 'otavio@exemplo.com.br']);
        $this->mover($this->vagaA, $revelado, 'entrevista');

        $resposta = $this->mover($this->vagaA, $this->candidato, 'entrevista', 'quadro', json: true)->assertOk();
        $this->assertStringContainsString(self::NOME, $resposta->json('message'));
        $this->assertStringContainsString(self::NOME, json_encode($resposta->json('data.quadro-vaga'), JSON_UNESCAPED_UNICODE));

        $volta = $this->mover($this->vagaA, $this->candidato, 'triagem', 'quadro', json: true)
            ->assertUnprocessable()
            ->assertHeader('Cache-Control', 'no-store, private');
        $volta->assertJsonValidationErrors('etapa')->assertJsonMissingPath('data');
        $this->assertSemIdentidade([$volta->getContent()]);
        $quadro = json_encode($this->getJson(route('vagas.show', $this->vagaA))->assertOk()->json('data.quadro-vaga'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString(self::NOME, $quadro);
        $this->assertStringContainsString('Otávio Brandão', $quadro);

        $perfil = $this->mover($this->vagaA, $this->candidato, 'triagem', 'perfil', json: true)->assertUnprocessable();
        $perfil->assertJsonMissingPath('fragments');
        $this->assertSemIdentidade([$perfil->getContent()]);
        $fragmentoPerfil = $this->perfil($this->vagaB)->assertOk()->getContent();
        $this->assertStringContainsString('Identidade protegida na triagem anônima', $fragmentoPerfil);
        $this->assertSemIdentidade([$fragmentoPerfil]);
        $this->assertStringNotContainsString('https://github.com/fernanda-demo', $fragmentoPerfil);
        $this->assertStringNotContainsString('Otávio Brandão', $fragmentoPerfil);

        $quadroB = json_encode($this->getJson(route('vagas.show', $this->vagaB))->assertOk()->json('data.quadro-vaga'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertSemIdentidade([$quadroB]);
        $this->assertSemIdentidade([$quadroB], $revelado);
    }

    public function test_perfil_revelado_em_json_traz_identidade_e_historico(): void
    {
        $resposta = $this->mover($this->vagaA, $this->candidato, 'entrevista', 'perfil', json: true)->assertOk();

        $fragmento = $resposta->json('fragments.perfil-na-vaga');
        $this->assertStringContainsString(self::NOME, $fragmento);
        $this->assertStringContainsString(self::EMAIL, $fragmento);
        $this->assertStringContainsString('Movido para Entrevista por Recrutadora Teste', $fragmento);
        $this->assertStringNotContainsString('<html', $fragmento);
    }
}
