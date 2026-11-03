<?php

namespace App\Services\Curriculo;

use App\DTOs\DadosExtraidosDTO;
use App\Enums\TipoCandidato;
use App\Models\Candidato;
use App\Models\Competencia;
use App\Services\CandidatoService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Primeira etapa da importação (research R6): guarda o PDF em área temporária privada, lê e
 * extrai os dados e mantém tudo na sessão até a confirmação. Nenhum candidato é criado aqui.
 *
 * @phpstan-type Importacao array{
 *     arquivo: string,
 *     nome_original: string,
 *     tamanho: int,
 *     tipo: string,
 *     situacao: string,
 *     texto: ?string,
 *     dados: array<string, mixed>,
 *     adicionadas: list<int>,
 *     lote?: ?string,
 * }
 */
class ImportacaoCurriculoService
{
    private const CHAVE_SESSAO = 'importacoes';

    private const LIMITE_TEXTO = 200_000;

    public function __construct(
        private LeitorPdf $leitor,
        private ExtratorCurriculo $extrator,
        private CandidatoService $candidatos,
        private Session $sessao,
    ) {}

    public function importar(UploadedFile $arquivo, TipoCandidato $tipo, ?string $lote = null): string
    {
        $uuid = (string) Str::uuid();
        $disco = Storage::disk('curriculos');
        $caminho = $disco->putFileAs('tmp', $arquivo, "{$uuid}.pdf");

        if ($caminho === false) {
            throw new RuntimeException('Não foi possível guardar o currículo enviado.');
        }

        $leitura = $this->leitor->extrairTexto($disco->path($caminho));
        $dados = $leitura->foiLido() ? $this->extrator->extrair((string) $leitura->texto, $leitura->destaque) : DadosExtraidosDTO::vazio();

        $this->sessao->put($this->chave($uuid), [
            'arquivo' => $caminho,
            'nome_original' => Str::limit($arquivo->getClientOriginalName(), 200, ''),
            'tamanho' => (int) $arquivo->getSize(),
            'tipo' => $tipo->value,
            'situacao' => $leitura->situacao,
            'texto' => $leitura->texto === null ? null : mb_substr($leitura->texto, 0, self::LIMITE_TEXTO),
            'dados' => $dados->toArray(),
            'adicionadas' => [],
            'lote' => $lote,
        ]);

        return $uuid;
    }

    /**
     * @return Importacao|null
     */
    public function obter(string $uuid): ?array
    {
        if (! Str::isUuid($uuid)) {
            return null;
        }

        return $this->sessao->get($this->chave($uuid));
    }

    public function duplicado(?string $email, ?string $cpf): ?Candidato
    {
        return $this->candidatos->encontrarPorEmailOuCpf($email, $cpf);
    }

    /**
     * Marca uma competência recém-incluída no catálogo para voltar selecionada na revisão.
     */
    public function marcarCompetenciaAdicionada(string $uuid, int $competenciaId): void
    {
        $importacao = $this->obter($uuid);

        if ($importacao === null) {
            return;
        }

        $importacao['adicionadas'] = array_values(array_unique([...$importacao['adicionadas'], $competenciaId]));
        $this->sessao->put($this->chave($uuid), $importacao);
    }

    /**
     * Encerra a importação: apaga o PDF temporário (se ainda existir) e limpa a sessão.
     */
    public function descartar(string $uuid): void
    {
        $importacao = $this->obter($uuid);

        if ($importacao === null) {
            return;
        }

        Storage::disk('curriculos')->delete($importacao['arquivo']);
        $this->sessao->forget($this->chave($uuid));
    }

    /**
     * Dados que a tela de revisão precisa, já com os valores iniciais do formulário.
     *
     * @param  Importacao  $importacao
     * @return array{
     *     dados: DadosExtraidosDTO,
     *     situacao: string,
     *     duplicado: ?Candidato,
     *     catalogo: array{hard: Collection<int, Competencia>, soft: Collection<int, Competencia>},
     *     valores: array<string, mixed>,
     *     selecionadas: list<int>,
     *     extraidas: list<int>,
     *     trechos: array<int, string>,
     *     sugestoes: list<string>,
     * }
     */
    public function dadosRevisao(array $importacao): array
    {
        $dados = DadosExtraidosDTO::fromArray($importacao['dados']);
        $catalogo = $this->candidatos->catalogoCompetencias();
        $nomesNoCatalogo = collect([...$catalogo['hard'], ...$catalogo['soft']])
            ->map(fn (Competencia $competencia): string => NormalizadorTexto::normalizar($competencia->nome))
            ->all();

        return [
            'dados' => $dados,
            'situacao' => $importacao['situacao'],
            'duplicado' => $this->duplicado($dados->email, $dados->cpf),
            'catalogo' => $catalogo,
            'valores' => [
                'nome' => $dados->nome,
                'email' => $dados->email,
                'telefone' => $dados->telefone,
                'cpf' => $dados->cpf,
                'data_nascimento' => $dados->data_nascimento,
                'tipo' => $importacao['tipo'],
                'area' => $dados->area_sugerida,
                'experiencia_na_area' => $dados->experiencia_na_area,
                'formacoes' => $dados->formacoes,
                'experiencias' => $dados->experiencias,
                'projetos' => $dados->projetos,
                'links' => $dados->links,
                'consentimento_meio' => null,
                'consentimento_em' => null,
            ],
            'selecionadas' => array_values(array_unique([...$dados->idsCompetencias(), ...$importacao['adicionadas']])),
            'extraidas' => $dados->idsCompetencias(),
            'trechos' => $dados->trechosPorCompetencia(),
            'sugestoes' => array_values(array_filter(
                $dados->sugestoes_competencias,
                fn (string $sugestao): bool => ! in_array(NormalizadorTexto::normalizar($sugestao), $nomesNoCatalogo, true),
            )),
        ];
    }

    private function chave(string $uuid): string
    {
        return self::CHAVE_SESSAO.'.'.$uuid;
    }
}
