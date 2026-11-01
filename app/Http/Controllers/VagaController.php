<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalvarVagaRequest;
use App\Models\Vaga;
use App\Services\VagaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use stdClass;

class VagaController extends Controller
{
    public function __construct(private VagaService $vagas) {}

    public function index(): View
    {
        return view('vagas.index', $this->vagas->listar());
    }

    public function create(): View
    {
        return view('vagas.create', [
            'vaga' => new Vaga,
            'catalogo' => $this->vagas->catalogo(),
            'pesos' => [],
        ]);
    }

    public function store(SalvarVagaRequest $request): RedirectResponse|JsonResponse
    {
        $vaga = $this->vagas->criar($request->dadosVaga(), $request->competenciasComPeso(), $request->user());

        return $this->navegarParaQuadro($request, $vaga, 'Vaga criada.');
    }

    /**
     * Quadro da vaga. Em AJAX ("Atualizar quadro") devolve só a região `quadro-vaga`, com a
     * mesma leitura e ordenação da página completa.
     */
    public function show(Request $request, Vaga $vaga): Response|JsonResponse
    {
        $quadro = $this->vagas->quadro($vaga);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => null,
                'fragments' => ['quadro-vaga' => view('vagas.partials._quadro', $quadro)->render()],
                'redirect' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('vagas.show', $quadro)->header('Cache-Control', 'private, no-store');
    }

    public function edit(Vaga $vaga): View|RedirectResponse
    {
        if (! $vaga->estaAberta()) {
            return redirect()->route('vagas.show', $vaga)->withErrors(['vaga' => VagaService::MENSAGEM_VAGA_ENCERRADA]);
        }

        return view('vagas.edit', [
            'vaga' => $vaga,
            'catalogo' => $this->vagas->catalogo(),
            'pesos' => $this->vagas->pesosAtuais($vaga),
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
}
