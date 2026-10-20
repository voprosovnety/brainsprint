<?php

namespace App\Funnel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisitorContext
{
    public const EXPERIMENT = 'landing-curiosity-v1';

    public const UTM = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];

    public static function id(Request $request): string
    {
        $id = $request->session()->get('visitor_id') ?? bin2hex(random_bytes(32));
        $request->session()->put('visitor_id', $id);

        return $id;
    }

    public static function capture(Request $request, array $attribution = []): array
    {
        Validator::make(['attribution' => $attribution], [
            'attribution' => ['array:'.implode(',', self::UTM)],
            'attribution.*' => ['string', 'max:120', 'regex:/^[^\x00-\x1F\x7F]*$/u'],
        ])->validate();
        $visitor = self::id($request);
        // Deterministic keyed assignment also survives simultaneous first requests.
        $experiment = $request->session()->get('experiment') ?? [
            'id' => self::EXPERIMENT,
            'variant' => hexdec(substr(hash_hmac('sha256', $visitor.self::EXPERIMENT, config('app.key')), 0, 2)) % 2 ? 'B' : 'A',
        ];
        $request->session()->put('experiment', $experiment);
        if (! $request->session()->has('attribution')) {
            $request->session()->put('attribution', array_filter($attribution, fn ($v) => $v !== ''));
        }

        return [
            'visitor_id' => $visitor, 'experiment_id' => $experiment['id'], 'variant' => $experiment['variant'],
            'attribution' => json_encode((object) $request->session()->get('attribution')),
            'is_synthetic' => app()->environment(['local', 'testing']),
        ];
    }

    public static function run(object $run): array
    {
        return [
            'visitor_id' => $run->visitor_id, 'funnel_version_id' => $run->funnel_version_id,
            'experiment_id' => $run->experiment_id, 'variant' => $run->variant,
            'attribution' => $run->attribution, 'is_synthetic' => $run->is_synthetic,
        ];
    }
}
