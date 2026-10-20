<?php

namespace App\Http\Controllers;

use App\Analytics\AggregateService;
use App\Analytics\Dataset;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DemoAnalyticsController extends Controller
{
    public function __invoke(Request $request, AggregateService $service)
    {
        // No client-selected bounds, dataset, fields, or identifiers. Public means synthetic only.
        abort_if(count($request->query()) > 0, 422, 'Demo analytics does not accept query parameters.');
        $asOf = CarbonImmutable::now('UTC');
        $summary = $service->summarize($asOf->subDays(7), $asOf, $asOf, Dataset::Synthetic, 24);

        return response()->json(collect($summary)->only(['dataset', 'cohort', 'totals', 'stage_conversions', 'quiz_elapsed_seconds', 'experiment', 'v3'])->all());
    }
}
