<?php

use App\Http\Middleware\SessionBoundary;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SessionBoundary::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            $status = $e instanceof ValidationException ? 422 : ($e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500);
            $codes = [404 => 'not_found', 409 => 'state_conflict', 419 => 'csrf_expired', 422 => 'invalid_answer', 429 => 'rate_limited', 503 => 'unavailable'];
            $message = match ($status) {
                419 => 'Your session needs refreshing. Reload this page and try again.',
                422 => 'Check your answer and try again.',
                429 => 'Too many requests. Wait a minute and try again.',
                500 => 'Your progress could not be saved. Please try again shortly.',
                default => $e->getMessage(),
            };

            return response()->json(['error' => ['code' => $codes[$status] ?? 'server_error', 'message' => $message, 'fields' => $e instanceof ValidationException ? $e->errors() : (object) []]], $status, array_merge($e instanceof HttpExceptionInterface ? $e->getHeaders() : [], ['Cache-Control' => 'private, no-store']));
        });
    })->create();
