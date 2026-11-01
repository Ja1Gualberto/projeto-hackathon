<?php

namespace Tests\Unit;

use App\Services\Selecao\LimpadorTexto;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

/**
 * Limpeza dos textos livres exibidos na triagem anônima (research R9, contracts/visibilidade-por-contexto.md).
 */
class LimpadorTextoTest extends TestCase
{
    private function limpar(string $texto, array $termos = []): string
    {
        return (new LimpadorTexto)->limpar($texto, $termos);
    }

    public function test_remove_emails_e_urls(): void
    {
        $this->assertSame(
            'Contato: [oculto] · portfólio [oculto] e [oculto].',
            $this->limpar('Contato: ana.lima@exemplo.com.br · portfólio https://ana.dev/projetos?x=1 e www.exemplo.com.br.'),
        );
        $this->assertSame('Código em [oculto]', $this->limpar('Código em github.com/ana-lima-demo'));
    }

    #[TestWith(['(69) 99876-5432'])]
    #[TestWith(['69 99876-5432'])]
    #[TestWith(['+55 69 3222-1234'])]
    #[TestWith(['99876-5432'])]
    public function test_remove_telefones(string $telefone): void
    {
        $this->assertSame('Ligar para [oculto] à tarde', $this->limpar("Ligar para {$telefone} à tarde"));
    }

    #[TestWith(['529.982.247-25'])]
    #[TestWith(['52998224725'])]
    public function test_remove_cpf_com_ou_sem_mascara(string $cpf): void
    {
        $this->assertSame('CPF [oculto]', $this->limpar("CPF {$cpf}"));
    }

    public function test_preserva_periodos_anos_e_numeros_comuns(): void
    {
        $texto = 'Estágio 2022-2023 na Secretaria (fictícia), 20 horas semanais, 3 sistemas e nota 9,5.';

        $this->assertSame($texto, $this->limpar($texto));
    }

    public function test_remove_os_termos_sem_diferenciar_maiusculas_nem_acentos(): void
    {
        $this->assertSame(
            'Projeto do [oculto] com [oculto] e [oculto]; Analista mantida.',
            $this->limpar('Projeto do JOSÉ ÁVILA com Jose e josé; Analista mantida.', ['José Ávila', 'José']),
        );
    }

    public function test_termo_so_e_removido_como_palavra_inteira(): void
    {
        $this->assertSame(
            '[oculto] Barreto? Não: [oculto]. Limão e limalha ficam.',
            $this->limpar('Lima Barreto? Não: Lima. Limão e limalha ficam.', ['Lima']),
        );
    }

    public function test_termos_vazios_sao_ignorados(): void
    {
        $this->assertSame('Texto sem dados pessoais.', $this->limpar('Texto sem dados pessoais.', ['', '  ']));
    }
}
