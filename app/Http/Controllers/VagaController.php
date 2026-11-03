<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarVagaRequest;
use App\Models\Vaga;
use App\Services\VagaService;
use App\Services\VagaTelaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Js;
use Illuminate\View\View;
use stdClass;

class VagaController extends Controller
{
    public function __construct(private VagaService $vagas, private VagaTelaService $tela) {}

    public function index(): View
    {
        return view('vagas.index', ['dadosTela' => $this->jsonInicial($this->tela->lista($this->vagas->listar()))]);
    }

    public function create(Request $request): View
    {
        return view('vagas.create', [
            'dadosTela' => $this->dadosFormulario($request, new Vaga, []),
        ]);
    }

    public function store(SalvarVagaRequest $request): RedirectResponse|JsonResponse
    {
        $vaga = $this->vagas->criar($request->dadosVaga(), $request->competenciasComPeso(), $request->user());

        return $this->navegarParaQuadro($request, $vaga, 'Vaga criada.');
    }

    /** O HTML é estático; a leitura inicial e as atualizações usam os mesmos dados. */
    public function show(Request $request, Vaga $vaga): Response|JsonResponse
    {
        $quadro = $this->tela->quadro($this->vagas->quadro($vaga));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => null,
                'fragments' => new stdClass,
                'data' => ['quadro-vaga' => $quadro],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('vagas.show', [
            'vaga' => $vaga,
            'dadosTela' => $this->jsonInicial([...$quadro, 'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? []]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function edit(Request $request, Vaga $vaga): View|RedirectResponse
    {
        if (! $vaga->estaAberta()) {
            return redirect()->route('vagas.show', $vaga)->withErrors(['vaga' => VagaService::MENSAGEM_VAGA_ENCERRADA]);
        }

        return view('vagas.edit', [
            'vaga' => $vaga,
            'dadosTela' => $this->dadosFormulario($request, $vaga, $this->vagas->pesosAtuais($vaga)),
        ]);
    }

    public function update(SalvarVagaRequest $request, Vaga $vaga): RedirectResponse|JsonResponse
    {
        $this->vagas->atualizar($vaga, $request->dadosVaga(), $request->competenciasComPeso());

        return $this->navegarParaQuadro($request, $vaga, 'Vaga atualizada. O ranking foi recalculado.');
    }

    private function navegarParaQuadro(Request $request, Vaga $vaga, string $mensagem): RedirectResponse|JsonResponse
    {
        $destino = route('vagas.show', $vaga);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $mensagem,
                'fragments' => new stdClass,
                'redirect' => $destino,
            ])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->to($destino)->with('status', $mensagem);
    }

    /** @param array<int, int> $pesos */
    private function dadosFormulario(Request $request, Vaga $vaga, array $pesos): string
    {
        return $this->jsonInicial([
            ...$this->tela->formulario($vaga, $this->vagas->catalogo(), $pesos),
            'oldInput' => $request->session()->hasOldInput() ? Arr::only($request->session()->getOldInput(), ['titulo', 'area', 'descricao', 'competencias', 'energia']) : null,
            'errors' => $request->session()->get('errors')?->getBag('default')->messages() ?? [],
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function jsonInicial(array $dados): string
    {
        return Js::encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
