<?php

namespace Database\Seeders;

use App\Enums\Area;
use App\Enums\StatusVaga;
use App\Enums\TipoCandidatoVaga;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Vagas fictícias da demonstração (T095). A vaga "Estagiário de Desenvolvimento" não é semeada:
 * ela é criada ao vivo no pitch (quickstart, cenário C). T112 (US4) e T121 (US5) completam este
 * seeder com movimentações e anotações.
 */
class VagaSeeder extends Seeder
{
    /**
     * @var list<array{titulo: string, area: Area, tipo_candidato: TipoCandidatoVaga, descricao: string, competencias: array<string, int>}>
     */
    private const VAGAS = [
        [
            'titulo' => 'Analista de Suporte Técnico',
            'area' => Area::Suporte,
            'tipo_candidato' => TipoCandidatoVaga::Servidor,
            'descricao' => 'Atendimento aos usuários das secretarias, gestão de chamados e manutenção das estações de trabalho.',
            'competencias' => ['Atendimento ao público' => 3, 'Windows' => 3, 'ITIL' => 2, 'Comunicação' => 2, 'Proatividade' => 1],
        ],
        [
            'titulo' => 'Analista de Dados',
            'area' => Area::Dados,
            'tipo_candidato' => TipoCandidatoVaga::Ambos,
            'descricao' => 'Construção de painéis de indicadores e apoio às áreas na análise de dados públicos.',
            'competencias' => ['SQL' => 3, 'Power BI' => 3, 'Python' => 2, 'Pensamento analítico' => 1, 'Inglês intermediário' => 1],
        ],
        [
            'titulo' => 'Estagiário de Infraestrutura',
            'area' => Area::Infraestrutura,
            'tipo_candidato' => TipoCandidatoVaga::Estagiario,
            'descricao' => 'Apoio à equipe de redes e servidores na instalação, no monitoramento e na documentação.',
            'competencias' => ['Linux' => 3, 'Redes de Computadores' => 3, 'Suporte a Hardware' => 2, 'Trabalho em equipe' => 1],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recrutador = User::query()->where('email', 'recrutador@setic.demo')->value('id');

        foreach (self::VAGAS as $dados) {
            $vaga = Vaga::query()->updateOrCreate(['titulo' => $dados['titulo']], [
                'area' => $dados['area'],
                'tipo_candidato' => $dados['tipo_candidato'],
                'descricao' => $dados['descricao'],
                'status' => StatusVaga::Aberta,
                'encerrada_em' => null,
                'criada_por' => $recrutador,
            ]);

            $vaga->competencias()->sync($this->pivos($dados['competencias']));
        }
    }

    /**
     * @param  array<string, int>  $pesos
     * @return array<int, array{peso: int}>
     */
    private function pivos(array $pesos): array
    {
        $ids = Competencia::query()->whereIn('nome', array_keys($pesos))->pluck('id', 'nome');

        $pivos = [];

        foreach ($pesos as $nome => $peso) {
            if (! isset($ids[$nome])) {
                throw new RuntimeException("Competência \"{$nome}\" não está no catálogo. Rode o CompetenciaSeeder antes.");
            }

            $pivos[$ids[$nome]] = ['peso' => $peso];
        }

        return $pivos;
    }
}
