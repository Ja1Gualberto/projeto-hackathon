<?php

namespace App\Http\Controllers;

use App\Http\Requests\CadastrarLoteRequest;
use App\Services\Curriculo\LoteImportacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use stdClass;

class LoteImportacaoController extends Controller
{
    public function __construct(private LoteImportacaoService $lotes) {}

    public function show(Request $request, string $lote): Response
    {
        $resumo = $this->lotes->resumo($lote);

        if ($resumo === null) {
            abort_if($request->expectsJson(), 404);

            return response()->view('importacoes.expirada', [], 404)->header('Cache-Control', 'private, no-store');
        }

        return response()->view('importacoes.lote', [
            'lote' => $lote,
            'resumo' => $resumo,
            'abrirEscolha' => $request->session()->pull('abrir_escolha_lote', false) && $resumo['pendentes'] > 0,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function cadastrar(CadastrarLoteRequest $request, string $lote): RedirectResponse|JsonResponse
    {
        abort_unless($this->lotes->existe($lote), 404);

        $resultado = $this->lotes->cadastrarSemRevisao($lote, $request->validated(), $request->user());
        $mensagem = $this->mensagem($resultado['cadastrados'], $resultado['pendentes']);
        $destino = route('importacoes.lotes.show', $lote);

        if ($request->expectsJson()) {
            $request->session()->flash('status', $mensagem);

            return response()->json([
                'message' => null,
                'fragments' => new stdClass,
                'redirect' => $destino,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->to($destino)->with('status', $mensagem);
    }

    private function mensagem(int $cadastrados, int $pendentes): string
    {
        $salvos = trans_choice('{0} Nenhum candidato cadastrado.|{1} 1 candidato cadastrado.|[2,*] :count candidatos cadastrados.', $cadastrados);

        if ($pendentes === 0) {
            return $salvos;
        }

        return $salvos.' '.trans_choice('{1} 1 currículo precisa de revisão manual.|[2,*] :count currículos precisam de revisão manual.', $pendentes);
    }
}
