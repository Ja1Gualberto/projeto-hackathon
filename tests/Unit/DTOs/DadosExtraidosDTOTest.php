<?php

namespace Tests\Unit\DTOs;

use App\DTOs\DadosExtraidosDTO;
use PHPUnit\Framework\TestCase;

class DadosExtraidosDTOTest extends TestCase
{
    public function test_vazio_nao_inventa_dados_e_trata_experiencia_como_nao_informada(): void
    {
        $vazio = DadosExtraidosDTO::vazio();

        $this->assertNull($vazio->nome);
        $this->assertSame([], $vazio->competencias);
        $this->assertSame([], $vazio->sugestoes_competencias);
        $this->assertFalse($vazio->experiencia_na_area);
    }

    public function test_ida_e_volta_pela_sessao_preserva_todos_os_campos(): void
    {
        $dados = new DadosExtraidosDTO(
            nome: 'Ana Lima',
            email: 'ana.lima@exemplo.com.br',
            telefone: '(69) 99876-5432',
            cpf: '52998224725',
            data_nascimento: '2004-03-14',
            formacoes: ['Sistemas de Informação'],
            experiencias: ['Estágio com Python'],
            projetos: ['Painel de chamados'],
            links: ['https://github.com/analima'],
            competencias: [['id' => 1, 'nome' => 'Python', 'categoria' => 'hard', 'trecho' => 'Estágio com Python']],
            sugestoes_competencias: ['Kubernetes'],
            area_sugerida: 'desenvolvimento',
            experiencia_na_area: true,
        );

        $array = $dados->toArray();

        $this->assertSame([
            'nome', 'email', 'telefone', 'cpf', 'data_nascimento', 'formacoes', 'experiencias', 'projetos',
            'links', 'competencias', 'sugestoes_competencias', 'area_sugerida', 'experiencia_na_area',
        ], array_keys($array));
        $this->assertEquals($dados, DadosExtraidosDTO::fromArray($array));
        $this->assertSame([1], $dados->idsCompetencias());
    }

    public function test_from_array_tolera_campos_ausentes(): void
    {
        $this->assertEquals(DadosExtraidosDTO::vazio(), DadosExtraidosDTO::fromArray([]));
    }
}
