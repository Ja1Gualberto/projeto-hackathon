<?php

namespace Tests\Unit;

use App\Services\Energia\ProximidadeEnergia;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ProximidadeEnergiaTest extends TestCase
{
    /**
     * A basic unit test example.
     */
    #[TestWith([[2, 4, 2], [3, 4, 1], 83])]
    #[TestWith([[3, 4, 1], [3, 4, 1], 100])]
    #[TestWith([[1, 1, 1], [5, 5, 5], 0])]
    public function test_proximidade_compara_os_tres_eixos(array $vaga, array $candidato, int $esperado): void
    {
        $this->assertSame($esperado, (new ProximidadeEnergia)->calcular($vaga, $candidato));
    }
}
