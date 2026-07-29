<?php

use App\Support\ApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // API v2: nessuna sessione/CSRF, path identici al legacy.
            Route::group([], base_path('routes/api_v2.php'));

            // Endpoint speciali per singole agenzie (fuori contratto v2).
            Route::group([], base_path('routes/api_v2_custom.php'));

            // Pannello agenzie (sottodominio) — registrato PRIMA del dominio
            // principale così le route di agencies vincono sul suo host.
            Route::domain(config('hybrid.domain_agencies'))
                ->middleware('web')
                ->group(base_path('routes/agencies.php'));

            // Sito principale.
            Route::domain(config('hybrid.domain_main'))
                ->middleware('web')
                ->group(base_path('routes/main.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'auth.jwt' => \App\Http\Middleware\AuthenticateJwt::class,
            'operatore.timeout' => \App\Http\Middleware\OperatoreSessionTimeout::class,
            'agencies.superadmin' => \App\Http\Middleware\AgenciesSuperAdmin::class,
        ]);

        // Ospiti non autenticati → /login (entrambi i domini hanno questa route,
        // url() usa l'host della richiesta corrente)
        $middleware->redirectGuestsTo(fn () => url('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApi = fn (Request $request): bool => $request->is('res/api/*');

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $isApi($request) || $request->expectsJson()
        );

        // Formato errore identico alla v2 legacy su tutte le route API.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null; // rendering di default per le pagine web
            }

            return match (true) {
                $e instanceof NotFoundHttpException => ApiResponse::err('Risorsa non trovata.', 'NOT_FOUND', 404),
                $e instanceof MethodNotAllowedHttpException => ApiResponse::err('Metodo non consentito.', 'METHOD_NOT_ALLOWED', 405),
                $e instanceof HttpExceptionInterface => ApiResponse::err(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Errore.',
                    'ERROR',
                    $e->getStatusCode(),
                ),
                default => ApiResponse::err(
                    config('app.debug') ? $e->getMessage() : 'Errore interno del server.',
                    'ERROR',
                    500,
                ),
            };
        });
    })->create();
