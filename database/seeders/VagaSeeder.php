<?php

namespace Database\Seeders;

use App\Enums\Area;
use App\Enums\EtapaSelecao;
use App\Enums\StatusVaga;
use App\Enums\TipoCandidatoVaga;
use App\Models\Anotacao;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Models\User;
use App\Models\Vaga;
use App\Repositories\Interfaces\ParticipacaoRepositoryInterface;
use App\Services\Anotacoes\AnotacaoService;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Vagas fictícias da demonstração (T095). A vaga "Estagiário de Desenvolvimento" não é semeada:
 * ela é criada ao vivo no pitch (quickstart, cenário C). Na "Analista de Dados", dois servidores já
 * estão em entrevista (T112) e com anotações registradas (T121).
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
     * Candidatos já em entrevista no quadro semeado (T112): S3 e S9 do CandidatoSeeder.
     *
     * @var array<string, list<string>>
     */
    private const EM_ENTREVISTA = [
        'Analista de Dados' => ['Bruno Peixoto', 'Renata Barros'],
    ];

    /**
     * Anotações das entrevistas semeadas (T121), com a competência observada abaixo.
     *
     * @var array<string, array<string, string>>
     */
    private const ANOTACOES = [
        'Analista de Dados' => [
            'Bruno Peixoto' => 'Entrevista técnica: explicou com clareza como montou um painel de indicadores em Power BI e respondeu às dúvidas da equipe com objetividade.',
            'Renata Barros' => 'Entrevista técnica: apresentou uma análise em Python sobre dados públicos fictícios e comunicou bem as conclusões para quem não é da área.',
        ],
    ];

    private const COMPETENCIA_OBSERVADA = 'Comunicação';

    /**
     * Run the database seeds.
     */
    public function run(ParticipacaoRepositoryInterface $participacoes, AnotacaoService $anotacoes): void
    {
        $recrutador = User::query()->where('email', 'recrutador@setic.demo')->first();
        $vagas = [];

        foreach (self::VAGAS as $dados) {
            $vaga = Vaga::query()->updateOrCreate(['titulo' => $dados['titulo']], [
                'area' => $dados['area'],
                'tipo_candidato' => $dados['tipo_candidato'],
                'descricao' => $dados['descricao'],
                'status' => StatusVaga::Aberta,
                'encerrada_em' => null,
                'criada_por' => $recrutador?->id,
            ]);

            $vaga->competencias()->sync($this->pivos($dados['competencias']));
            $vagas[$vaga->titulo] = $vaga;
        }

        if ($recrutador !== null) {
            $this->moverParaEntrevista($vagas, $participacoes, $recrutador);
            $this->registrarAnotacoes($vagas, $anotacoes, $recrutador);
        }
    }

    /**
     * Pelo AnotacaoService, como na tela: a competência observada passa a ter origem `entrevista`.
     * Uma anotação igual já registrada não é repetida.
     *
     * @param  array<string, Vaga>  $vagas
     */
    private function registrarAnotacoes(array $vagas, AnotacaoService $anotacoes, User $recrutador): void
    {
        $observadas = Competencia::query()->where('nome', self::COMPETENCIA_OBSERVADA)->pluck('id')->all();

        foreach (self::ANOTACOES as $titulo => $textos) {
            foreach (Candidato::query()->whereIn('nome', array_keys($textos))->get() as $candidato) {
                $texto = $textos[$candidato->nome];
                $registrada = Anotacao::query()
                    ->where('candidato_id', $candidato->id)
                    ->where('vaga_id', $vagas[$titulo]->id)
                    ->where('texto', $texto)
                    ->exists();

                if (! $registrada) {
                    $anotacoes->registrar($candidato, $vagas[$titulo], $recrutador, $texto, $observadas);
                }
            }
        }
    }

    /**
     * Usa o mesmo caminho da tela, que registra quem moveu. Rodar de novo não duplica nada, e
     * candidatos ausentes (seeder de candidatos não executado) são ignorados.
     *
     * @param  array<string, Vaga>  $vagas
     */
    private function moverParaEntrevista(array $vagas, ParticipacaoRepositoryInterface $participacoes, User $recrutador): void
    {
        foreach (self::EM_ENTREVISTA as $titulo => $nomes) {
            foreach (Candidato::query()->whereIn('nome', $nomes)->get() as $candidato) {
                $participacoes->moverPara($vagas[$titulo], $candidato, EtapaSelecao::Entrevista, $recrutador);
            }
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
