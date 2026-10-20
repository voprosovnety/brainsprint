<?php

namespace App\Http\Controllers;

use App\Funnel\VisitorContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrivacyController extends Controller
{
    public function __invoke(Request $request)
    {
        $payload = json_decode($request->getContent());
        abort_if(! $payload instanceof \stdClass || get_object_vars($payload) || $request->query->count(), 422, 'Send an empty JSON object to delete this browser’s data.');
        $visitor = VisitorContext::id($request);
        DB::table('events')->where('visitor_id', $visitor)->delete();
        DB::table('funnel_runs')->where('visitor_id', $visitor)->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['deleted' => true]);
    }
}
