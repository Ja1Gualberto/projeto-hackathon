{{--
    Paginação em português e nos tokens do design system. Substitui a view padrão do Laravel, que
    traz textos em inglês e variantes de tema escuro que a aplicação não tem. Links reais: sem
    JavaScript navegam normalmente; com JavaScript, busca.js os intercepta em [data-paginacao-busca].
--}}
@php
    $classesItem = 'flex min-h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-semibold';
    $classesLink = $classesItem.' text-primaria-700 ring-1 ring-borda ring-inset transition-colors hover:bg-primaria-50 motion-reduce:transition-none';
@endphp

@if ($paginator->hasPages())
    <nav aria-label="Paginação dos resultados" class="flex flex-col items-center justify-between gap-3 border-t border-borda pt-4 sm:flex-row">
        <p class="text-sm text-texto-suave">
            Mostrando <strong class="font-semibold text-texto tabular-nums">{{ $paginator->firstItem() }}</strong>
            a <strong class="font-semibold text-texto tabular-nums">{{ $paginator->lastItem() }}</strong>
            de <strong class="font-semibold text-texto tabular-nums">{{ $paginator->total() }}</strong>
        </p>
        <ul class="flex flex-wrap items-center justify-center gap-1">
            <li>
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" class="{{ $classesItem }} text-texto-suave">Anterior</span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $classesLink }}">Anterior</a>
                @endif
            </li>
            @foreach ($elements as $elemento)
                @if (is_string($elemento))
                    <li aria-hidden="true" class="px-1 text-texto-suave">…</li>
                @else
                    @foreach ($elemento as $pagina => $url)
                        <li>
                            @if ($pagina === $paginator->currentPage())
                                <span aria-current="page" class="{{ $classesItem }} bg-primaria-600 text-white tabular-nums">{{ $pagina }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="Ir para a página {{ $pagina }}" class="{{ $classesLink }} tabular-nums">{{ $pagina }}</a>
                            @endif
                        </li>
                    @endforeach
                @endif
            @endforeach
            <li>
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $classesLink }}">Próxima</a>
                @else
                    <span aria-disabled="true" class="{{ $classesItem }} text-texto-suave">Próxima</span>
                @endif
            </li>
        </ul>
    </nav>
@endif
