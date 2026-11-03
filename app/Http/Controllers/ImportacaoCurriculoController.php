<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportarCurriculoRequest;
use App\Repositories\Interfaces\VagaRepositoryInterface;
use App\Services\Curriculo\ImportacaoCurriculoService;
use App\Services\Curriculo\LoteImportacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportacaoCurriculoController extends Controller
{
    public function __construct(
        private ImportacaoCurriculoService $importacoes,
        private LoteImportacaoService $lotes,
        private VagaRepositoryInterface $vagas,
    ) {}

    public function create(Request $request): View
    {
        $vagas = $this->vagas->listarAbertas();
        $vagaSelecionada = $request->integer('vaga_id');

        return view('importacoes.create', [
            'vagas' => $vagas,
            'vagaSelecionada' => $vagaSelecionada,
            'vagaFixa' => $vagas->contains('id', $vagaSelecionada),
        ]);
    }

    public function store(ImportarCurriculoRequest $request): RedirectResponse|JsonResponse
    {
        $arquivos = array_values($request->file('arquivos'));
        $vagaId = (int) $request->validated('vaga_id');

        if (count($arquivos) === 1) {
            $uuid = $this->importacoes->importar($arquivos[0], $vagaId);

            return $this->responder($request, 'Currículo lido. Revise os dados antes de salvar.', route('importacoes.show', $uuid));
        }

        $lote = $this->lotes->criar($arquivos, $vagaId);
        $request->session()->put('abrir_escolha_lote', true);

        return $this->responder($request, 'Currículos lidos. Escolha como continuar.', route('importacoes.lotes.show', $lote));
    }

    public function show(Request $request, string $importacao): Response
    {
        $dados = $this->importacoes->obter($importacao);

        if ($dados === null) {
            abort_if($request->expectsJson(), 404);

            return response()->view('importacoes.expirada', [], 404)->header('Cache-Control', 'private, no-store');
        }

        return response()->view('importacoes.show', [
            'uuid' => $importacao,
            'importacao' => $dados,
            'vagaRelacionada' => isset($dados['vaga_id']) ? $this->vagas->encontrar($dados['vaga_id']) : null,
            'posicaoLote' => isset($dados['lote']) ? $this->lotes->posicao($dados['lote'], $importacao) : null,
            ...$this->importacoes->dadosRevisao($dados),
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * PDF temporário da revisão: só existe na sessão de quem o enviou, até a confirmação ou o descarte.
     */
    public function pdf(string $importacao): StreamedResponse
    {
        $dados = $this->importacoes->obter($importacao);

        abort_if($dados === null || ! Storage::disk('curriculos')->exists($dados['arquivo']), 404);

        return Storage::disk('curriculos')->response($dados['arquivo'], $dados['nome_original'], [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function destroy(Request $request, string $importacao): RedirectResponse|JsonResponse
    {
        $dados = $this->importacoes->obter($importacao);

        abort_if($dados === null, 404);

        $this->importacoes->descartar($importacao);

        if (! isset($dados['lote'])) {
            return $this->responder($request, 'Currículo descartado.', route('importacoes.create'));
        }

        $this->lotes->marcarDescartado($dados['lote'], $importacao);

        return $this->responder($request, 'Currículo descartado.', route('importacoes.lotes.show', $dados['lote']));
    }

    private function responder(Request $request, string $mensagem, string $destino): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => new stdClass,
                'redirect' => $destino,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->to($destino);
    }
}
