<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('painel'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (PostTooLargeException $exception, Request $request): ?Response {
            if (! $request->expectsJson()) {
                return response()->view('errors.413', [], 413);
            }

            return null;
        });
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();
            $mensagens = [
                401 => 'Sua sessão expirou. Entre novamente para continuar.',
                403 => 'Você não tem permissão para acessar este recurso.',
                404 => 'O recurso solicitado não foi encontrado.',
                419 => 'Sua sessão expirou. Atualize a página antes de continuar.',
                413 => 'O arquivo excede o limite de 10 MB.',
                503 => 'Análise indisponível no momento.',
            ];

            if (isset($mensagens[$status]) || $status >= 500) {
                return response()->json(['message' => $mensagens[$status] ?? 'Não foi possível concluir a operação. Tente consultar o estado antes de continuar.'], $status, [
                    'Cache-Control' => 'private, no-store',
                ]);
            }

            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        });
    })->create();
