<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PerfilCandidatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_perfil_organiza_curriculo_avaliacoes_e_administracao_em_quatro_paineis_sem_ids_duplicados(): void
    {
        $candidato = Candidato::factory()->estagiario()->create([
            'formacoes' => ['Bacharelado em Sistemas de Informação'],
            'experiencias' => ['Desenvolvimento de sistemas públicos'],
            'projetos' => ['Portal de dados abertos'],
            'email' => 'candidata@example.com',
        ]);
        $competencia = Competencia::factory()->hard()->create(['nome' => 'PHP']);
        $candidato->competencias()->attach($competencia, ['origem' => 'entrevista']);

        $resposta = $this->actingAs(User::factory()->create())->get(route('candidatos.show', $candidato));

        $resposta->assertOk()
            ->assertSee('Bacharelado em Sistemas de Informação')
            ->assertSee('Desenvolvimento de sistemas públicos')
            ->assertSee('Portal de dados abertos')
            ->assertSee('candidata@example.com')
            ->assertSee('PHP')
            ->assertSee('observada');
        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(4, $xpath->query('//nav[@data-abas-perfil]/a[@data-aba-perfil]'));
        $this->assertCount(4, $xpath->query('//section[@data-painel-perfil][not(@hidden)]'));
        $this->assertCount(0, $xpath->query('//*[@id][@id = preceding::*/@id]'));
        $this->assertCount(1, $xpath->query('//section[@id="visao-geral"]//aside[@aria-label="Contato e documentos"]'));
        $this->assertCount(1, $xpath->query('//section[@id="entrevistas"]//div[@id="competencias-candidato"]'));
        $this->assertCount(1, $xpath->query('//section[@id="entrevistas"]//div[@id="secao-energia"]'));
        $this->assertCount(1, $xpath->query('//section[@id="historico"]//div[@id="secao-lgpd"]'));
        $this->assertCount(0, $xpath->query('//section[@id="visao-geral"]//form'));
    }

    public function test_aba_curriculo_mostra_metadados_e_previa_sob_demanda_pela_rota_protegida(): void
    {
        $candidato = Candidato::factory()->create([
            'curriculo_arquivo' => 'curriculo.pdf',
            'curriculo_nome_original' => 'curriculo-profissional.pdf',
            'curriculo_tamanho' => 2048,
        ]);

        $resposta = $this->actingAs(User::factory()->create())->get(route('candidatos.show', $candidato));

        $resposta->assertOk()->assertSee('curriculo-profissional.pdf')->assertSee('2,0 KB');
        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(1, $xpath->query('//section[@id="curriculo"]//iframe[@data-pdf-perfil][not(@src)][@data-src="'.route('candidatos.curriculo', $candidato).'"][@loading="lazy"][@title]'));
        $this->assertCount(1, $xpath->query('//a[@href="'.route('candidatos.curriculo', $candidato).'"]'));
    }

    public function test_ausencia_de_pdf_mostra_estado_vazio_sem_previa_e_historico_nao_inventa_eventos(): void
    {
        $candidato = Candidato::factory()->create(['dados_atualizados_em' => '2026-10-25 14:30:00']);

        $resposta = $this->actingAs(User::factory()->create())->get(route('candidatos.show', $candidato));

        $resposta->assertOk()
            ->assertSee('Nenhum PDF anexado.')
            ->assertSee('25/10/2026 14:30')
            ->assertSee('O sistema não mantém uma trilha detalhada de alterações de dados e de status.')
            ->assertDontSee('data-pdf-perfil', false);
    }

    #[TestWith(['texto', 'entrevistas'])]
    #[TestWith(['competencias_observadas.0', 'entrevistas'])]
    #[TestWith(['justificativa', 'entrevistas'])]
    #[TestWith(['confirmacao', 'historico'])]
    #[TestWith(['status', 'historico'])]
    public function test_erro_de_validacao_identifica_painel_que_precisa_ser_revelado(string $campo, string $painel): void
    {
        $candidato = Candidato::factory()->create();
        $erros = (new ViewErrorBag)->put('default', new MessageBag([$campo => 'Revise o campo informado.']));

        $resposta = $this->view('candidatos.show', [
            'candidato' => $candidato,
            'errors' => $erros,
            'anotacoes' => collect(),
            'vagas' => collect(),
            'catalogo' => ['hard' => collect(), 'soft' => collect()],
        ]);

        $resposta->assertSee('data-painel-com-erros', false);
        $documento = new \DOMDocument;
        $documento->loadHTML((string) $resposta, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $this->assertCount(1, $xpath->query('//section[@id="'.$painel.'"][@data-painel-com-erros]'));
    }
}
