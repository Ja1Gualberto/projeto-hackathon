<?php

namespace App\Services\Curriculo;

use App\DTOs\DadosExtraidosDTO;
use App\Enums\OrigemCompetencia;
use App\Models\Candidato;
use App\Models\User;
use App\Repositories\Interfaces\CandidatoRepositoryInterface;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use App\Services\Selecao\SelecaoService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Segunda etapa da importação: transforma a revisão confirmada em cadastro (contracts/extracao-curriculo.md).
 * Também atende a correção manual de dados (US6, FR-029).
 */
class CadastroCandidatoService
{
    /** Campos que a revisão humana pode definir. */
    private const CAMPOS_REVISADOS = [
        'nome', 'email', 'telefone', 'cpf', 'data_nascimento', 'area', 'experiencia_na_area',
        'formacoes', 'experiencias', 'projetos', 'links', 'consentimento_meio', 'consentimento_em',
    ];

    public function __construct(
        private CandidatoRepositoryInterface $candidatos,
        private SelecaoService $selecao,
        private VagaRepositoryInterface $vagas,
    ) {}

    /**
     * Cria o candidato (modo `novo`) ou atualiza o cadastro duplicado (modo `atualizar`).
     * O PDF temporário vira o currículo original; no modo `atualizar`, o PDF anterior é
     * apagado só depois do commit. Competências nunca têm a origem rebaixada.
     *
     * @param  array{arquivo: string, nome_original: string, tamanho: int, texto: ?string, dados: array<string, mixed>, vaga_id: int}  $importacao
     * @param  array<string, mixed>  $revisados  Dados já validados por ConfirmarImportacaoRequest.
     */
    public function confirmar(array $importacao, array $revisados, User $recrutador): Candidato
    {
        $vaga = isset($importacao['vaga_id']) ? $this->vagas->encontrar($importacao['vaga_id']) : null;

        if ($vaga === null) {
            throw ValidationException::withMessages(['vaga' => 'O processo seletivo não está disponível. Importe o currículo novamente selecionando uma vaga.']);
        }

        $existente = ($revisados['modo'] ?? 'novo') === 'atualizar'
            ? $this->cadastroParaAtualizar($importacao, $revisados)
            : null;
        $arquivoAnterior = $existente?->curriculo_arquivo;

        $disco = Storage::disk('curriculos');
        $destino = Str::uuid().'.pdf';

        if (! $disco->copy($importacao['arquivo'], $destino)) {
            throw new RuntimeException('Não foi possível guardar o currículo original.');
        }

        $atributos = [
            ...$this->atributosRevisados($revisados),
            'texto_curriculo' => $importacao['texto'],
            'curriculo_arquivo' => $destino,
            'curriculo_nome_original' => $importacao['nome_original'],
            'curriculo_tamanho' => $importacao['tamanho'],
            'consentimento_registrado_por' => $recrutador->id,
            'dados_atualizados_em' => now(),
        ];
        $revisadas = $this->origensRevisadas(
            $revisados['competencias'] ?? [],
            DadosExtraidosDTO::fromArray($importacao['dados'])->idsCompetencias(),
        );

        try {
            $candidato = DB::transaction(function () use ($existente, $atributos, $revisadas, $vaga): Candidato {
                $this->vagas->encontrar($vaga->id, bloquear: true);

                if ($existente === null) {
                    $candidato = $this->candidatos->criar($atributos);
                    $this->candidatos->sincronizarCompetencias($candidato, $revisadas);
                } else {
                    $origens = $this->mesclarSemRebaixar($this->candidatos->origensDasCompetencias($existente), $revisadas);
                    $candidato = $this->candidatos->atualizar($existente, $atributos);
                    $this->candidatos->sincronizarCompetencias($candidato, $origens);
                }

                $this->selecao->adicionarATriagem($vaga, [$candidato->id]);

                return $candidato;
            });
        } catch (Throwable $erro) {
            $disco->delete($destino);

            throw $erro;
        }

        $disco->delete(array_values(array_filter([$importacao['arquivo'], $arquivoAnterior])));

        return $candidato;
    }

    /**
     * Correção manual dos dados (US6). Competências mantidas conservam a origem; as novas entram
     * como "adicionada na revisão"; as desmarcadas são removidas.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizar(Candidato $candidato, array $dados): Candidato
    {
        return DB::transaction(function () use ($candidato, $dados): Candidato {
            $atuais = $this->candidatos->origensDasCompetencias($candidato);
            $origens = [];

            foreach (array_map('intval', $dados['competencias'] ?? []) as $id) {
                $origens[$id] = $atuais[$id] ?? OrigemCompetencia::Revisao;
            }

            $candidato = $this->candidatos->atualizar($candidato, [
                ...$this->atributosRevisados($dados),
                'dados_atualizados_em' => now(),
            ]);
            $this->candidatos->sincronizarCompetencias($candidato, $origens);

            return $candidato;
        });
    }

    /**
     * O cadastro escolhido precisa corresponder ao e-mail ou CPF do currículo (lido ou revisado),
     * para a importação não sobrescrever outra pessoa.
     *
     * @param  array{dados: array<string, mixed>}  $importacao
     * @param  array<string, mixed>  $revisados
     */
    private function cadastroParaAtualizar(array $importacao, array $revisados): Candidato
    {
        $candidato = $this->candidatos->encontrar((int) ($revisados['candidato_existente_id'] ?? 0));
        $extraidos = DadosExtraidosDTO::fromArray($importacao['dados']);

        $emails = array_filter(array_map(
            fn (?string $email): ?string => $email === null ? null : mb_strtolower(trim($email)),
            [$revisados['email'] ?? null, $extraidos->email],
        ));
        $cpfs = array_filter([$revisados['cpf'] ?? null, $extraidos->cpf]);

        $corresponde = $candidato !== null && (
            ($candidato->email !== null && in_array(mb_strtolower($candidato->email), $emails, true))
            || ($candidato->cpf !== null && in_array($candidato->cpf, $cpfs, true))
        );

        if (! $corresponde) {
            throw ValidationException::withMessages([
                'candidato_existente_id' => 'O cadastro escolhido não corresponde ao e-mail ou CPF deste currículo.',
            ]);
        }

        return $candidato;
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function atributosRevisados(array $dados): array
    {
        $atributos = Arr::only($dados, self::CAMPOS_REVISADOS);

        if (array_key_exists('email', $atributos) && $atributos['email'] !== null) {
            $atributos['email'] = mb_strtolower($atributos['email']);
        }

        if (array_key_exists('experiencia_na_area', $atributos)) {
            $atributos['experiencia_na_area'] = (bool) $atributos['experiencia_na_area'];
        }

        return $atributos;
    }

    /**
     * @param  list<int|string>  $ids  Competências marcadas na revisão.
     * @param  list<int>  $extraidas  Competências que a leitura automática encontrou no PDF.
     * @return array<int, OrigemCompetencia>
     */
    private function origensRevisadas(array $ids, array $extraidas): array
    {
        $origens = [];

        foreach (array_map('intval', $ids) as $id) {
            $origens[$id] = in_array($id, $extraidas, true) ? OrigemCompetencia::Curriculo : OrigemCompetencia::Revisao;
        }

        return $origens;
    }

    /**
     * União das competências atuais com as revisadas; uma origem de prioridade maior
     * (ex.: observada em entrevista) nunca é substituída por uma menor.
     *
     * @param  array<int, OrigemCompetencia>  $atuais
     * @param  array<int, OrigemCompetencia>  $revisadas
     * @return array<int, OrigemCompetencia>
     */
    private function mesclarSemRebaixar(array $atuais, array $revisadas): array
    {
        foreach ($revisadas as $id => $origem) {
            if (! isset($atuais[$id]) || $origem->prioridade() > $atuais[$id]->prioridade()) {
                $atuais[$id] = $origem;
            }
        }

        return $atuais;
    }
}
