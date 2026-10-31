<?php

namespace App\Http\Controllers;

use App\Services\PainelService;
use Illuminate\View\View;

class PainelController extends Controller
{
    public function __invoke(PainelService $painel): View
    {
        return view('painel.index', $painel->resumo());
    }
}
