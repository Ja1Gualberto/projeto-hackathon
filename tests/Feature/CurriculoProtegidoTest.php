<?php

namespace Tests\Feature;

use App\Models\Candidato;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * PDF original no contexto da vaga (invariantes 2 e 8 de contracts/visibilidade-por-contexto.md, FR-023).
 */
class CurriculoProtegidoTest extends TestCase
{
    use RefreshDatabase;

    private Candidato $candidato;

    private Vaga $vaga;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('curriculos');
        Storage::disk('curriculos')->put('cv.pdf', '%PDF-1.4 conteúdo fictício');
        $this->candidato = Candidato::factory()->estagiario()->create([
            'curriculo_arquivo' => 'cv.pdf',
            'curriculo_nome_original' => 'curriculo.pdf',
        ]);
        $this->vaga = Vaga::factory()->paraEstagiarios()->create();
        $this->vaga->participacoes()->create(['candidato_id' => $this->candidato->id, 'etapa' => 'triagem']);
        $this->actingAs(User::factory()->create());
    }

    private function curriculoNaVaga(?Vaga $vaga = null, ?string $codigo = null): string
    {
        return route('vagas.candidatos.curriculo', [$vaga ?? $this->vaga, $codigo ?? $this->candidato->codigo]);
    }

    private function mover(string $etapa, ?Vaga $vaga = null): void
    {
        ($vaga ?? $this->vaga)->participacoes()->firstOrCreate(['candidato_id' => $this->candidato->id], ['etapa' => 'triagem']);
        $this->patch(route('vagas.candidatos.etapa', [$vaga ?? $this->vaga, $this->candidato->codigo]), ['etapa' => $etapa])
            ->assertSessionHasNoErrors();
    }

    public function test_perfil_do_banco_mostra_um_unico_link_do_pdf_no_topo_junto_da_edicao(): void
    {
        $resposta = $this->get(route('candidatos.show', $this->candidato));

        $resposta->assertOk()->assertSee('curriculo.pdf');
        $documento = new \DOMDocument;
        $documento->loadHTML($resposta->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        $xpath = new \DOMXPath($documento);
        $linkPdf = 'a[@href="'.route('candidatos.curriculo', $this->candidato).'"]';
        $this->assertCount(1, $xpath->query('//'.$linkPdf));
        $this->assertCount(1, $xpath->query('//div[@data-perfil-candidato]/header//'.$linkPdf.'/following-sibling::a[@href="'.route('candidatos.edit', $this->candidato).'"]'));
    }

    public function test_pdf_responde_403_em_triagem_inclusive_por_acesso_direto(): void
    {
        $this->get($this->curriculoNaVaga())
            ->assertForbidden()
            ->assertSee('Identidade protegida na triagem anônima')
            ->assertDontSee('%PDF', false);

        $this->getJson($this->curriculoNaVaga())
            ->assertForbidden()
            ->assertExactJson(['message' => 'Você não tem permissão para acessar este recurso.']);
    }

    #[TestWith(['entrevista'])]
    #[TestWith(['aprovado'])]
    #[TestWith(['reprovado'])]
    public function test_pdf_abre_a_partir_da_entrevista(string $etapa): void
    {
        $this->mover($etapa);

        $resposta = $this->get($this->curriculoNaVaga())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertStringContainsString('inline', $resposta->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 conteúdo fictício', $resposta->streamedContent());
    }

    public function test_retorno_a_triagem_e_rejeitado_e_nao_altera_acesso_ao_pdf(): void
    {
        $this->mover('entrevista');
        $this->get($this->curriculoNaVaga())->assertOk();

        $this->patchJson(route('vagas.candidatos.etapa', [$this->vaga, $this->candidato->codigo]), ['etapa' => 'triagem'])
            ->assertUnprocessable()->assertJsonValidationErrors('etapa');

        $this->get($this->curriculoNaVaga())->assertOk();
    }

    public function test_entrevista_em_outra_vaga_nao_libera_o_pdf(): void
    {
        $outra = Vaga::factory()->paraEstagiarios()->create();
        $this->mover('entrevista', $outra);

        $this->get($this->curriculoNaVaga($outra))->assertOk();
        $this->get($this->curriculoNaVaga())->assertForbidden();
    }

    public function test_sem_arquivo_responde_404_mesmo_revelado(): void
    {
        $this->mover('entrevista');
        Storage::disk('curriculos')->delete('cv.pdf');

        $this->get($this->curriculoNaVaga())->assertNotFound();
    }

    public function test_codigo_inexistente_responde_404(): void
    {
        $codigo = collect(range(0, 9999))
            ->map(fn (int $numero): string => 'CAND-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT))
            ->first(fn (string $codigo): bool => $codigo !== $this->candidato->codigo);

        $this->get($this->curriculoNaVaga(codigo: $codigo))->assertNotFound();
        $this->get(route('vagas.candidatos.show', [$this->vaga, $codigo]))->assertNotFound();
    }

    public function test_visitante_e_redirecionado_para_o_login_nas_duas_rotas_de_pdf(): void
    {
        $this->app['auth']->forgetGuards();

        $this->get(route('candidatos.curriculo', $this->candidato))->assertRedirect(route('login'));
        $this->get($this->curriculoNaVaga())->assertRedirect(route('login'));
        $this->get(route('vagas.candidatos.show', [$this->vaga, $this->candidato->codigo]))->assertRedirect(route('login'));
        $this->getJson($this->curriculoNaVaga())->assertUnauthorized();
    }
}
