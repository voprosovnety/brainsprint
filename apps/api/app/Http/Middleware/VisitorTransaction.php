<?php

namespace App\Http\Middleware;

use App\Funnel\VisitorContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class VisitorTransaction
{
    public function handle(Request $request, Closure $next)
    {
        DB::beginTransaction();
        try {
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [VisitorContext::id($request)]);
            $response = $next($request);
            // Laravel's routing pipeline can render an exception before it reaches us.
            // Roll back error responses too, including PostgreSQL aborted transactions.
            if ($response->getStatusCode() >= 400) {
                DB::rollBack();
            } else {
                DB::commit();
            }

            return $response;
        } catch (Throwable $exception) {
            DB::rollBack();
            throw $exception;
        }
    }
}
