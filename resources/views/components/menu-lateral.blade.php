@php
    $grupos = [
        ['rotulo' => 'Recrutamento', 'itens' => [
            ['rota' => 'painel', 'ativo' => 'painel', 'rotulo' => 'Painel', 'icone' => 'painel'],
            ['rota' => 'candidatos.index', 'ativo' => 'candidatos.*', 'rotulo' => 'Candidatos', 'icone' => 'grupo'],
            ['rota' => 'vagas.index', 'ativo' => 'vagas.*', 'rotulo' => 'Vagas', 'icone' => 'vaga'],
        ]],
        ['rotulo' => 'Currículos', 'itens' => [
            ['rota' => 'importacoes.create', 'ativo' => 'importacoes.*', 'rotulo' => 'Importar currículo', 'icone' => 'upload'],
        ]],
    ];
@endphp

<aside id="menu-lateral" aria-label="Menu do Banco de Talentos" class="z-50 flex flex-col overflow-hidden border-b border-primaria-800 bg-primaria-900 text-white transition-[width,translate,box-shadow] duration-200 ease-out motion-reduce:transition-none max-lg:group-data-[menu-pronto]/menu:fixed max-lg:group-data-[menu-pronto]/menu:inset-y-0 max-lg:group-data-[menu-pronto]/menu:left-0 max-lg:group-data-[menu-pronto]/menu:w-72 max-lg:group-data-[menu-pronto]/menu:max-w-[calc(100vw-3rem)] max-lg:group-data-[menu-aberto=false]/menu:-translate-x-full max-lg:group-data-[menu-aberto=true]/menu:translate-x-0 lg:fixed lg:inset-y-0 lg:left-0 lg:w-64 lg:border-r lg:border-b-0 lg:group-data-[menu-recolhido=false]/menu:shadow-[12px_0_32px_-16px_rgb(21_47_87/0.4)] lg:group-data-[menu-recolhido=true]/menu:w-20 lg:group-data-[menu-recolhido=true]/menu:cursor-pointer">
    <div class="flex min-h-24 shrink-0 items-center px-4">
        <a href="{{ route('painel') }}" data-menu-identidade aria-label="BT Banco de Talentos SETIC — início" title="Banco de Talentos SETIC — início" class="flex min-w-0 items-center gap-3 rounded-xl">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-primaria-300/30 bg-primaria-800 text-lg font-bold tracking-tight text-white">BT</span>
            <span class="min-w-0 whitespace-nowrap leading-tight lg:group-data-[menu-recolhido=true]/menu:sr-only">
                <span class="block text-sm font-bold">Banco de Talentos</span>
                <span class="mt-1 block text-xs text-primaria-200">SETIC</span>
            </span>
        </a>
    </div>

    <nav aria-label="Navegação principal" class="flex flex-1 flex-col gap-5 overflow-x-hidden overflow-y-auto overscroll-contain px-3 pb-6">
        @foreach ($grupos as $grupo)
            <div class="border-t border-white/15 pt-4">
                <p class="mb-2 px-3 text-xs font-medium whitespace-nowrap text-primaria-200 lg:group-data-[menu-recolhido=true]/menu:invisible">{{ $grupo['rotulo'] }}</p>
                <div class="flex flex-col gap-1">
                    @foreach ($grupo['itens'] as $item)
                        @if (Route::has($item['rota']))
                            @php($ativo = request()->routeIs($item['ativo']))
                            <a
                                href="{{ route($item['rota']) }}"
                                aria-label="{{ $item['rotulo'] }}"
                                title="{{ $item['rotulo'] }}"
                                @if ($ativo) aria-current="page" @endif
                                @class([
                                    'relative flex min-h-12 shrink-0 items-center gap-3 rounded-lg px-3 text-sm font-medium whitespace-nowrap transition-colors motion-reduce:transition-none',
                                    'bg-primaria-800 text-white before:absolute before:inset-y-3 before:-left-3 before:w-0.5 before:rounded-r before:bg-primaria-200' => $ativo,
                                    'text-primaria-100 hover:bg-white/10 hover:text-white' => ! $ativo,
                                ])
                            >
                                <x-icone :nome="$item['icone']" class="size-6" />
                                <span class="lg:group-data-[menu-recolhido=true]/menu:sr-only">{{ $item['rotulo'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="mt-auto shrink-0 border-t border-white/15 p-3">
        <div class="flex min-h-14 items-center gap-3 px-3" title="{{ auth()->user()?->name }}">
            <x-icone nome="usuario" class="size-6 text-primaria-200" />
            <div class="min-w-0 lg:group-data-[menu-recolhido=true]/menu:sr-only">
                <p class="truncate text-sm font-semibold">{{ auth()->user()?->name }}</p>
                <p class="text-xs text-primaria-200">Minha sessão</p>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" aria-label="Sair da conta" title="Sair da conta" class="flex min-h-12 w-full cursor-pointer items-center gap-3 rounded-lg px-3 text-sm font-medium text-primaria-100 transition-colors hover:bg-white/10 hover:text-white motion-reduce:transition-none">
                <x-icone nome="sair" class="size-6" />
                <span class="lg:group-data-[menu-recolhido=true]/menu:sr-only">Sair da conta</span>
            </button>
        </form>
        <p class="mt-3 px-3 text-xs whitespace-nowrap text-primaria-200 lg:group-data-[menu-recolhido=true]/menu:invisible">
            <span class="lg:hidden">Toque fora para fechar</span>
            <span class="hidden lg:inline">Clique fora para recolher</span>
        </p>
    </div>
</aside>
