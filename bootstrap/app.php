<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Silence PHP 8.5 deprecation notices from Laravel 11 framework code (PDO::MYSQL_ATTR_SSL_CA — fixed upstream in Laravel 12+). Safe to remove when pinning Laravel >= 12 or PHP <= 8.4.
if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? getenv('APP_ENV')) !== 'testing') {
    error_reporting(error_reporting() & ~E_DEPRECATED & ~E_USER_DEPRECATED);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Weekly digest: every Monday at 09:00 in the app's timezone.
        $schedule->command('digest:send')->weeklyOn(1, '9:00');
    })
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // This is an API-only sample — always render exceptions as JSON under
        // /api/*, regardless of the incoming Accept header. Keeps validation
        // failures (422), not-found errors (404), and server errors (500) all
        // machine-parseable for curl / HTTP clients.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*'),
        );
    })
    ->create();
