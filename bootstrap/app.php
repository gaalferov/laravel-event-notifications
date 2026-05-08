<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withEvents(discover: [
        __DIR__.'/../app/Listeners',
    ])
    ->withSchedule(function (Schedule $schedule) {
        // Weekly digest: every Monday at 09:00 in the app's timezone.
        $schedule->command('digest:send')->weeklyOn(1, '9:00');
    })
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API-only sample. Render exceptions as JSON under /api/* regardless of
        // the incoming Accept header so validation failures (422), not-found
        // errors (404), and server errors (500) are machine-parseable.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*'),
        );
    })
    ->create();
