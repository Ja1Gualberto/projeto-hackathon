<?php

namespace Tests\Unit;

use App\Enums\EtapaSelecao;
use App\Enums\OrigemCompetencia;
use App\Enums\PesoCompetencia;
use App\Enums\TipoCandidato;
use App\Enums\TipoCandidatoVaga;
use PHPUnit\Framework\TestCase;

class EnumsDominioTest extends TestCase
{
    public function test_somente_entrevista_e_aprovado_revelam_a_identidade(): void
    {
        $this->assertFalse(EtapaSelecao::Triagem->revelaIdentidade());
        $this->assertTrue(EtapaSelecao::Entrevista->revelaIdentidade());
        $this->assertTrue(EtapaSelecao::Aprovado->revelaIdentidade());
    }

    public function test_tipo_da_vaga_define_quem_e_aceito(): void
    {
        $this->assertTrue(TipoCandidatoVaga::Estagiario->aceita(TipoCandidato::Estagiario));
        $this->assertFalse(TipoCandidatoVaga::Estagiario->aceita(TipoCandidato::Servidor));
        $this->assertTrue(TipoCandidatoVaga::Ambos->aceita(TipoCandidato::Servidor));
        $this->assertTrue(TipoCandidatoVaga::Ambos->aceita(TipoCandidato::Estagiario));
    }

    public function test_origem_observada_em_entrevista_tem_a_maior_prioridade(): void
    {
        $this->assertGreaterThan(OrigemCompetencia::Revisao->prioridade(), OrigemCompetencia::Entrevista->prioridade());
        $this->assertGreaterThan(OrigemCompetencia::Curriculo->prioridade(), OrigemCompetencia::Revisao->prioridade());
    }

    public function test_pesos_usam_a_escala_curta_de_um_a_tres(): void
    {
        $this->assertSame([1, 2, 3], array_map(fn (PesoCompetencia $peso): int => $peso->value, PesoCompetencia::cases()));
        $this->assertSame('Essencial', PesoCompetencia::Essencial->rotulo());
    }

    public function test_tipos_de_candidato_tem_rotulo_e_cor_distintos(): void
    {
        $this->assertSame('Estagiário', TipoCandidato::Estagiario->rotulo());
        $this->assertNotSame(TipoCandidato::Servidor->cor(), TipoCandidato::Estagiario->cor());
        $this->assertNotSame(TipoCandidato::Servidor->icone(), TipoCandidato::Estagiario->icone());
    }
}
