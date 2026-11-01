<?php

namespace Tests\Unit;

use App\DTOs\ResultadoAderenciaDTO;
use App\Enums\OrigemCompetencia;
use App\Models\Competencia;
use App\Services\Aderencia\CalculadoraAderencia;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Fórmula de aderência (FR-017, research R7): classe pura, sem banco de dados.
 */
class CalculadoraAderenciaTest extends TestCase
{
    private int $proximoId = 1;

    /** @var array<string, Competencia> */
    private array $catalogo = [];

    private function competencia(string $nome, ?string $grupo = null, ?int $nivel = null): Competencia
    {
        if (! isset($this->catalogo[$nome])) {
            $competencia = new Competencia(['nome' => $nome, 'grupo' => $grupo, 'nivel' => $nivel]);
            $competencia->id = $this->proximoId++;
            $this->catalogo[$nome] = $competencia;
        }

        return $this->catalogo[$nome];
    }

    /**
     * @param  array<string, int>  $pesos  Nome da competência => peso na vaga.
     * @return Collection<int, Competencia>
     */
    private function requisitos(array $pesos): Collection
    {
        return collect($pesos)->map(function (int $peso, string $nome): Competencia {
            $requisito = clone $this->competencia($nome);

            return $requisito->setRelation('pivot', new Pivot(['peso' => $peso]));
        })->values();
    }

    /**
     * @param  array<string, OrigemCompetencia|string>  $origens  Nome da competência => origem.
     * @return Collection<int, Competencia>
     */
    private function perfil(array $origens): Collection
    {
        return collect($origens)->map(function (OrigemCompetencia|string $origem, string $nome): Competencia {
            $competencia = clone $this->competencia($nome);
            $valor = $origem instanceof OrigemCompetencia ? $origem->value : $origem;

            return $competencia->setRelation('pivot', new Pivot(['origem' => $valor]));
        })->values();
    }

    /** @param array<string, int> $pesos */
    private function calcular(array $pesos, array $origens): ResultadoAderenciaDTO
    {
        return (new CalculadoraAderencia)->calcular($this->requisitos($pesos), $this->perfil($origens));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->competencia('Inglês básico', 'ingles', 1);
        $this->competencia('Inglês intermediário', 'ingles', 2);
        $this->competencia('Inglês avançado', 'ingles', 3);
    }

    /** @return array<string, int> */
    private function vagaDoRoteiro(): array
    {
        return ['Python' => 3, 'SQL' => 3, 'Git' => 2, 'Inglês básico' => 1, 'Trabalho em equipe' => 1];
    }

    public function test_formula_soma_os_pesos_atendidos_sobre_o_total(): void
    {
        $resultado = $this->calcular($this->vagaDoRoteiro(), [
            'Python' => 'curriculo',
            'SQL' => 'curriculo',
            'Inglês básico' => 'curriculo',
            'Trabalho em equipe' => 'entrevista',
        ]);

        $this->assertSame(80, $resultado->percentual);
        $this->assertSame(8, $resultado->pesoAtendido);
        $this->assertSame(10, $resultado->pesoTotal);
        $this->assertSame(
            '80% — tem 4 de 5 competências; não identificado: Git (peso 2)',
            $resultado->explicacao(),
        );
    }

    public function test_arredonda_para_o_inteiro_mais_proximo_com_meio_para_cima(): void
    {
        $this->assertSame(67, $this->calcular(['A' => 2, 'B' => 2, 'C' => 2], ['A' => 'curriculo', 'B' => 'curriculo'])->percentual);
        $this->assertSame(33, $this->calcular(['A' => 2, 'B' => 2, 'C' => 2], ['A' => 'curriculo'])->percentual);

        // 1 ÷ 8 × 100 = 12,5 → 13.
        $this->assertSame(13, $this->calcular(['A' => 1, 'B' => 3, 'C' => 2, 'D' => 2], ['A' => 'curriculo'])->percentual);
    }

    public function test_zero_por_cento_explica_que_nada_foi_identificado_no_curriculo(): void
    {
        $resultado = $this->calcular($this->vagaDoRoteiro(), ['Figma' => 'curriculo', 'Comunicação' => 'curriculo']);

        $this->assertSame(0, $resultado->percentual);
        $this->assertSame([], $resultado->atendidas);
        $this->assertCount(5, $resultado->naoIdentificadas);
        $this->assertStringContainsString('nenhuma competência solicitada foi identificada no currículo', $resultado->explicacao());
        $this->assertStringNotContainsString('não possui', $resultado->explicacao());
    }

    public function test_cem_por_cento_diz_que_tem_todas_as_competencias(): void
    {
        $resultado = $this->calcular($this->vagaDoRoteiro(), [
            'Python' => 'curriculo',
            'SQL' => 'revisao',
            'Git' => 'curriculo',
            'Inglês intermediário' => 'curriculo',
            'Trabalho em equipe' => 'entrevista',
            'Laravel' => 'curriculo',
        ]);

        $this->assertSame(100, $resultado->percentual);
        $this->assertSame([], $resultado->naoIdentificadas);
        $this->assertSame('100% — tem todas as 5 competências', $resultado->explicacao());
    }

    public function test_idioma_de_nivel_maior_atende_ao_nivel_menor_do_mesmo_grupo(): void
    {
        $avancadoAtendeBasico = $this->calcular(['Inglês básico' => 1], ['Inglês avançado' => 'curriculo']);
        $basicoNaoAtendeIntermediario = $this->calcular(['Inglês intermediário' => 2], ['Inglês básico' => 'curriculo']);

        $this->assertSame(100, $avancadoAtendeBasico->percentual);
        $this->assertSame('Inglês básico', $avancadoAtendeBasico->atendidas[0]['nome']);
        $this->assertSame(0, $basicoNaoAtendeIntermediario->percentual);
        $this->assertSame([['nome' => 'Inglês intermediário', 'peso' => 2]], $basicoNaoAtendeIntermediario->naoIdentificadas);
    }

    public function test_competencias_comuns_nao_se_atendem_por_nivel(): void
    {
        $this->competencia('SQL');
        $this->competencia('MySQL');

        $resultado = $this->calcular(['SQL' => 3], ['MySQL' => 'curriculo']);

        $this->assertSame(0, $resultado->percentual);
    }

    public function test_peso_essencial_atendido_soma_somente_os_pesos_tres(): void
    {
        $resultado = $this->calcular($this->vagaDoRoteiro(), [
            'Python' => 'curriculo',
            'Git' => 'curriculo',
            'Trabalho em equipe' => 'curriculo',
        ]);

        $this->assertSame(3, $resultado->pesoEssencialAtendido);
        $this->assertSame(6, $resultado->pesoAtendido);
    }

    public function test_atendidas_guardam_a_origem_e_o_peso_e_faltantes_vem_do_maior_peso(): void
    {
        $resultado = $this->calcular($this->vagaDoRoteiro(), [
            'Python' => OrigemCompetencia::Entrevista,
            'Inglês básico' => OrigemCompetencia::Revisao,
        ]);

        $this->assertSame(
            [
                ['nome' => 'Python', 'peso' => 3, 'origem' => OrigemCompetencia::Entrevista],
                ['nome' => 'Inglês básico', 'peso' => 1, 'origem' => OrigemCompetencia::Revisao],
            ],
            $resultado->atendidas,
        );
        $this->assertSame(
            '40% — tem 2 de 5 competências; não identificado: SQL (peso 3), Git (peso 2), Trabalho em equipe (peso 1)',
            $resultado->explicacao(),
        );
    }

    public function test_vaga_com_uma_competencia_tem_explicacao_no_singular(): void
    {
        $this->assertSame('100% — tem a competência solicitada', $this->calcular(['Python' => 3], ['Python' => 'curriculo'])->explicacao());
        $this->assertSame('0% — nenhuma competência solicitada foi identificada no currículo', $this->calcular(['Python' => 3], [])->explicacao());
    }
}
