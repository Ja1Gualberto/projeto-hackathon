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
    <x-central-alertas />
    <a href="#conteudo" class="sr-only rounded-lg bg-primaria-700 px-4 py-2 text-white focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50">
        Pular para o conteúdo
    </a>

    <div data-menu data-menu-recolhido="true" class="group/menu min-h-screen">
        <div data-menu-fundo hidden aria-hidden="true" class="fixed inset-0 z-40 bg-slate-950/45 lg:hidden"></div>
        <x-menu-lateral />

        <div data-menu-conteudo class="flex min-h-screen min-w-0 flex-col lg:pl-20">
            <header class="flex min-h-20 items-center gap-4 border-b border-borda bg-superficie px-4 sm:px-6">
                <a href="#menu-lateral" data-menu-abrir hidden aria-controls="menu-lateral" aria-expanded="false" aria-haspopup="dialog" aria-label="BT Abrir navegação do Banco de Talentos" title="Abrir navegação" class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primaria-900 text-sm font-bold tracking-tight text-white lg:hidden">BT</a>
                <div class="flex min-w-0 items-center gap-3 text-sm">
                    <span class="hidden border-r border-borda pr-3 font-semibold text-texto-suave sm:block">SETIC</span>
                    <span class="truncate font-semibold">@yield('titulo', 'Painel')</span>
                </div>
            </header>

            <main id="conteudo" tabindex="-1" class="mx-auto flex w-full @yield('largura-conteudo', 'max-w-7xl') flex-1 flex-col gap-6 py-8 @yield('espacamento-horizontal', 'px-4 sm:px-6')">
                <div data-ajax-anuncios role="status" aria-live="polite" aria-atomic="true" class="sr-only"></div>
                <a data-ajax-login href="{{ route('login') }}" target="_blank" rel="noopener" hidden class="text-sm font-semibold text-primaria-700 underline">Abrir login em outra aba (seus campos serão preservados)</a>
                <a data-ajax-conferir href="{{ url()->full() }}" target="_blank" rel="noopener" hidden class="text-sm font-semibold text-primaria-700 underline">Conferir estado em outra aba antes de repetir a operação</a>
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
