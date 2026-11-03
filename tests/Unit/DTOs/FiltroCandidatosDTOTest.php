<?php

namespace Tests\Unit\DTOs;

use App\DTOs\FiltroCandidatosDTO;
use App\Enums\Area;
use PHPUnit\Framework\TestCase;

class FiltroCandidatosDTOTest extends TestCase
{
    public function test_normaliza_filtros_validados_sem_perder_os_criterios(): void
    {
        $filtro = FiltroCandidatosDTO::fromArray([
            'competencias' => ['3', '7', '3'],
            'vaga_id' => '12',
            'area' => 'desenvolvimento',
            'experiencia' => '1',
            'busca' => '  Marina  ',
        ]);

        $this->assertSame([3, 7], $filtro->competencias);
        $this->assertSame(12, $filtro->vagaId);
        $this->assertSame(Area::Desenvolvimento, $filtro->area);
        $this->assertTrue($filtro->somenteComExperiencia);
        $this->assertSame('Marina', $filtro->busca);
        $this->assertSame('ativos', $filtro->situacao);
    }

    public function test_filtros_vazios_mantem_os_valores_padrao(): void
    {
        $filtro = FiltroCandidatosDTO::fromArray([]);

        $this->assertSame([], $filtro->competencias);
        $this->assertNull($filtro->vagaId);
        $this->assertNull($filtro->area);
        $this->assertFalse($filtro->somenteComExperiencia);
        $this->assertNull($filtro->busca);
        $this->assertSame('ativos', $filtro->situacao);
    }

    public function test_equivalencias_geram_novo_dto_sem_alterar_os_filtros_originais(): void
    {
        $original = FiltroCandidatosDTO::fromArray(['competencias' => ['3'], 'vaga_id' => '8']);

        $resolvido = $original->comCompetenciasEquivalentes([3 => [3, 4, 5]]);

        $this->assertNotSame($original, $resolvido);
        $this->assertSame([], $original->competenciasEquivalentes);
        $this->assertSame([3 => [3, 4, 5]], $resolvido->competenciasEquivalentes);
        $this->assertSame([3], $resolvido->competencias);
        $this->assertSame(8, $resolvido->vagaId);
    }
}
