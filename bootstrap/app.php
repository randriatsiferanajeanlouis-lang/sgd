<?php

use App\Http\Middleware\EnsureAccountIsUsable;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RestrictToInternalNetwork;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account.usable' => EnsureAccountIsUsable::class,
        ]);

        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->web(prepend: [RestrictToInternalNetwork::class], append: [SecurityHeaders::class, HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Base de données arrêtée, coupée ou table endommagée : message clair au lieu d'une trace technique.
        $exceptions->render(function (QueryException $exception, Request $request) {
            $code = (int) ($exception->errorInfo[1] ?? 0);

            if (! in_array($code, [2002, 2006, 2013, 1932, 1030, 1194, 1195], true)) {
                return null;
            }

            $message = in_array($code, [2002, 2006, 2013], true)
                ? 'La base de données est arrêtée ou injoignable. Si vous utilisez XAMPP, démarrez MariaDB puis actualisez la page ; sinon, prévenez l’administrateur.'
                : 'Une table de la base de données est endommagée. Vos données sont conservées : prévenez l’administrateur pour qu’il lance la réparation (php artisan db:verify).';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 503);
            }

            return response()->view('errors.503', ['exception' => new HttpException(503, $message)], 503);
        });
    })->create();
