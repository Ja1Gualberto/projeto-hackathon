<?php

namespace App\Services\Curriculo;

use App\DTOs\ResultadoLeituraDTO;
use App\Enums\MeioConsentimento;
use App\Http\Requests\ConfirmarImportacaoRequest;
use App\Models\Candidato;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lote de importação (specs/002, data-model.md): currículos enviados juntos, restritos à sessão.
 * Cada item é uma importação comum; nada vai para o banco antes da confirmação ou do cadastro sem
 * revisão. O cadastro sem revisão nunca decide por conta própria: duplicidades, leituras incertas
 * e dados recusados pelas validações ficam pendentes para o recrutador.
 *
 * @phpstan-type Item array{
 *     uuid: string,
 *     nome_original: string,
 *     situacao: 'pendente'|'cadastrado'|'descartado',
 *     motivo: ?string,
 *     candidato_id: ?int,
 *     candidato_nome: ?string,
 * }
 */
class LoteImportacaoService
{
    public const PENDENTE = 'pendente';

    public const CADASTRADO = 'cadastrado';

    public const DESCARTADO = 'descartado';

    private const CHAVE_SESSAO = 'importacoes_lotes';

    private const MOTIVO_FALHA = 'Não foi possível salvar este currículo. Revise e tente novamente.';

    public function __construct(
        private ImportacaoCurriculoService $importacoes,
        private CadastroCandidatoService $cadastro,
        private Session $sessao,
    ) {}

    /**
     * @param  list<UploadedFile>  $arquivos
     */
    public function criar(array $arquivos, int $vagaId): string
    {
        $lote = (string) Str::uuid();
        $itens = [];

        foreach ($arquivos as $arquivo) {
            $uuid = $this->importacoes->importar($arquivo, $vagaId, $lote);
            $itens[] = [
                'uuid' => $uuid,
                'nome_original' => $this->importacoes->obter($uuid)['nome_original'],
                'situacao' => self::PENDENTE,
                'motivo' => null,
                'candidato_id' => null,
                'candidato_nome' => null,
            ];
        }

        $this->guardar($lote, $itens);

        return $lote;
    }

    public function existe(string $lote): bool
    {
        return $this->itens($lote) !== null;
    }

    /**
     * Situação atual de cada item. Pendentes trazem o motivo por que precisam de revisão, avaliado
     * agora: outro item do lote pode ter sido salvo com o mesmo e-mail.
     *
     * @return array{
     *     itens: list<array<string, mixed>>,
     *     total: int,
     *     cadastrados: int,
     *     pendentes: int,
     *     aptos: int,
     *     proximo: ?string,
     * }|null
     */
    public function resumo(string $lote): ?array
    {
        $itens = $this->itens($lote);

        if ($itens === null) {
            return null;
        }

        $resumo = array_map(function (array $item): array {
            if ($item['situacao'] !== self::PENDENTE) {
                return [...$item, 'apto' => false];
            }

            $importacao = $this->importacoes->obter($item['uuid']);

            if ($importacao === null) {
                return [...$item, 'situacao' => self::DESCARTADO, 'apto' => false];
            }

            $motivo = $this->motivoPendencia($importacao);

            return [...$item, 'motivo' => $motivo ?? $item['motivo'], 'apto' => $motivo === null];
        }, $itens);

        $pendentes = array_values(array_filter($resumo, fn (array $item): bool => $item['situacao'] === self::PENDENTE));

        return [
            'itens' => $resumo,
            'total' => count($resumo),
            'cadastrados' => count(array_filter($resumo, fn (array $item): bool => $item['situacao'] === self::CADASTRADO)),
            'pendentes' => count($pendentes),
            'aptos' => count(array_filter($pendentes, fn (array $item): bool => $item['apto'])),
            'proximo' => $pendentes[0]['uuid'] ?? null,
        ];
    }

    /**
     * Posição de um currículo na fila, para a tela de revisão.
     *
     * @return array{lote: string, atual: int, total: int, proximo: ?string}|null
     */
    public function posicao(string $lote, string $uuid): ?array
    {
        $itens = $this->itens($lote);

        if ($itens === null) {
            return null;
        }

        $indice = array_search($uuid, array_column($itens, 'uuid'), true);

        return $indice === false ? null : [
            'lote' => $lote,
            'atual' => $indice + 1,
            'total' => count($itens),
            'proximo' => $this->proximoPendente($lote, $uuid),
        ];
    }

    /**
     * Próximo pendente depois de $uuid, voltando ao início da fila.
     */
    public function proximoPendente(string $lote, string $uuid): ?string
    {
        $itens = $this->itens($lote) ?? [];
        $indice = (int) array_search($uuid, array_column($itens, 'uuid'), true);
        $fila = [...array_slice($itens, $indice + 1), ...array_slice($itens, 0, $indice)];

        foreach ($fila as $item) {
            if ($item['situacao'] === self::PENDENTE && $this->importacoes->obter($item['uuid']) !== null) {
                return $item['uuid'];
            }
        }

        return null;
    }

    public function marcarCadastrado(string $lote, string $uuid, Candidato $candidato): void
    {
        $this->atualizarItem($lote, $uuid, [
            'situacao' => self::CADASTRADO,
            'motivo' => null,
            'candidato_id' => $candidato->id,
            'candidato_nome' => $candidato->nome,
        ]);
    }

    public function marcarDescartado(string $lote, string $uuid): void
    {
        $this->atualizarItem($lote, $uuid, ['situacao' => self::DESCARTADO, 'motivo' => null]);
    }

    /**
     * Cadastra os currículos aptos com os dados lidos. Uma falha não interrompe os demais.
     *
     * @param  array{consentimento_meio: string, consentimento_em: string}  $consentimento
     * @return array{cadastrados: int, pendentes: int}
     */
    public function cadastrarSemRevisao(string $lote, array $consentimento, User $recrutador): array
    {
        $cadastrados = 0;

        foreach ($this->itens($lote) ?? [] as $item) {
            $importacao = $item['situacao'] === self::PENDENTE ? $this->importacoes->obter($item['uuid']) : null;

            if ($importacao === null || $this->motivoPendencia($importacao) !== null) {
                continue;
            }

            try {
                $validados = $this->validador($importacao, $consentimento)->validate();
                $candidato = $this->cadastro->confirmar($importacao, $validados, $recrutador);
            } catch (Throwable $erro) {
                report($erro);
                $this->atualizarItem($lote, $item['uuid'], ['motivo' => self::MOTIVO_FALHA]);

                continue;
            }

            $this->importacoes->descartar($item['uuid']);
            $this->marcarCadastrado($lote, $item['uuid'], $candidato);
            $cadastrados++;
        }

        return ['cadastrados' => $cadastrados, 'pendentes' => $this->resumo($lote)['pendentes'] ?? 0];
    }

    /**
     * Por que o currículo não pode ser salvo sem revisão; nulo quando está apto.
     *
     * @param  array<string, mixed>  $importacao
     */
    private function motivoPendencia(array $importacao): ?string
    {
        $leitura = match ($importacao['situacao']) {
            ResultadoLeituraDTO::SEM_TEXTO => 'Não conseguimos ler o texto deste PDF (parece digitalizado).',
            ResultadoLeituraDTO::CORROMPIDO => 'O arquivo parece corrompido.',
            default => null,
        };

        if ($leitura !== null) {
            return $leitura;
        }

        $revisao = $this->importacoes->dadosRevisao($importacao);

        if ($revisao['valores']['nome'] === null) {
            return 'Nome não identificado.';
        }

        if ($revisao['valores']['area'] === null) {
            return 'Área de atuação não identificada.';
        }

        if ($revisao['duplicado'] !== null) {
            return "Possível cadastro duplicado: {$revisao['duplicado']->nome}.";
        }

        $validador = $this->validador($importacao, [
            'consentimento_meio' => MeioConsentimento::Outro->value,
            'consentimento_em' => now()->toDateString(),
        ]);

        return $validador->fails() ? 'Há dados que precisam de correção: '.$validador->errors()->first() : null;
    }

    /**
     * As mesmas regras da revisão manual, sobre os dados como foram lidos.
     *
     * @param  array<string, mixed>  $importacao
     * @param  array{consentimento_meio: string, consentimento_em: string}  $consentimento
     */
    private function validador(array $importacao, array $consentimento): ValidatorContract
    {
        $revisao = $this->importacoes->dadosRevisao($importacao);
        $regras = new ConfirmarImportacaoRequest;

        return Validator::make([
            ...$revisao['valores'],
            ...$consentimento,
            'competencias' => $revisao['selecionadas'],
            'modo' => 'novo',
        ], $regras->rules(), $regras->messages(), $regras->attributes());
    }

    /**
     * @return list<Item>|null
     */
    private function itens(string $lote): ?array
    {
        return Str::isUuid($lote) ? $this->sessao->get($this->chave($lote)) : null;
    }

    /**
     * @param  list<Item>  $itens
     */
    private function guardar(string $lote, array $itens): void
    {
        $this->sessao->put($this->chave($lote), $itens);
    }

    /**
     * @param  array<string, mixed>  $alteracoes
     */
    private function atualizarItem(string $lote, string $uuid, array $alteracoes): void
    {
        $itens = array_map(
            fn (array $item): array => $item['uuid'] === $uuid ? [...$item, ...$alteracoes] : $item,
            $this->itens($lote) ?? [],
        );

        $this->guardar($lote, $itens);
    }

    private function chave(string $lote): string
    {
        return self::CHAVE_SESSAO.'.'.$lote;
    }
}
