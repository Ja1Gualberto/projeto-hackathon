<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>
        @hasSection('titulo')
            @yield('titulo') ·
        @endif
        {{ config('app.name') }}
    </title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <a href="#conteudo" class="sr-only rounded-lg bg-primaria-700 px-4 py-2 text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50">
        Pular para o conteúdo
    </a>

    @php
        // Rotas de histórias ainda não implementadas aparecem como "#" até existirem.
        $itensNavegacao = [
            ['rota' => 'painel', 'ativo' => 'painel', 'rotulo' => 'Painel', 'icone' => 'painel'],
            ['rota' => 'candidatos.index', 'ativo' => 'candidatos.*', 'rotulo' => 'Candidatos', 'icone' => 'grupo'],
            ['rota' => 'importacoes.create', 'ativo' => 'importacoes.*', 'rotulo' => 'Importar currículo', 'icone' => 'upload'],
            ['rota' => 'vagas.index', 'ativo' => 'vagas.*', 'rotulo' => 'Vagas', 'icone' => 'vaga'],
        ];
    @endphp

    <div class="lg:flex">
        <aside class="border-b border-primaria-800 bg-primaria-900 text-white lg:fixed lg:inset-y-0 lg:w-64 lg:border-r lg:border-b-0">
            <div class="flex items-center gap-3 px-6 py-5">
                <span class="flex size-10 items-center justify-center rounded-xl bg-white text-lg font-bold text-primaria-800">BT</span>
                <div class="leading-tight">
                    <p class="font-bold">Banco de Talentos</p>
                    <p class="text-sm text-primaria-200">SETIC</p>
                </div>
            </div>

            <nav aria-label="Navegação principal" class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:pb-0">
                @foreach ($itensNavegacao as $item)
                    @php
                        $existe = Route::has($item['rota']);
                        $ativo = $existe && request()->routeIs($item['ativo']);
                    @endphp
                    <a
                        href="{{ $existe ? route($item['rota']) : '#' }}"
                        @if ($ativo) aria-current="page" @endif
                        @class([
                            'flex shrink-0 items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition-colors',
                            'bg-white/15 text-white' => $ativo,
                            'text-primaria-100 hover:bg-white/10 hover:text-white' => ! $ativo && $existe,
                            'cursor-not-allowed text-primaria-300' => ! $existe,
                        ])
                    >
                        <x-icone :nome="$item['icone']" />
                        {{ $item['rotulo'] }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="flex min-h-screen flex-1 flex-col lg:pl-64">
            <header class="flex items-center justify-end gap-4 border-b border-borda bg-superficie px-6 py-3">
                <span class="flex items-center gap-2 text-sm font-semibold text-texto-suave">
                    <x-icone nome="usuario" />
                    {{ auth()->user()?->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-botao type="submit" variante="fantasma" tamanho="sm">
                        <x-icone nome="sair" class="size-4" />
                        Sair
                    </x-botao>
                </form>
            </header>

            <main id="conteudo" class="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 px-6 py-8">
                @if (session('status'))
                    <x-alerta tipo="sucesso">{{ session('status') }}</x-alerta>
                @endif

                @yield('conteudo')
            </main>

            <footer class="border-t border-borda px-6 py-4 text-center text-sm text-texto-suave">
                Ambiente de demonstração — somente dados fictícios.
            </footer>
        </div>
    </div>
</body>
</html>
