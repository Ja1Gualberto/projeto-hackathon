<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>
        @hasSection('titulo')
            @yield('titulo') ·
        @endif
        {{ config('app.name') }}
    </title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-linear-to-br from-primaria-900 via-primaria-800 to-primaria-600">
    <main class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="flex w-full max-w-md flex-col gap-6">
            <div class="flex items-center justify-center gap-3 text-white">
                <span class="flex size-12 items-center justify-center rounded-xl bg-white text-xl font-bold text-primaria-800">BT</span>
                <div class="leading-tight">
                    <p class="text-xl font-bold">Banco de Talentos</p>
                    <p class="text-primaria-100">SETIC</p>
                </div>
            </div>

            <div class="rounded-card bg-superficie p-8 shadow-xl">
                @yield('conteudo')
            </div>
        </div>
    </main>
</body>
</html>
