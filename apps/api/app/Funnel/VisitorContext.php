<?php

namespace App\Funnel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisitorContext
{
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
        $request->session()->forget(['experiment', 'landing_version']);
        if (! $request->session()->has('attribution')) {
            $request->session()->put('attribution', array_filter($attribution, fn ($v) => $v !== ''));
        }

        return [
            'visitor_id' => $visitor,
            'attribution' => json_encode((object) $request->session()->get('attribution')),
            'is_synthetic' => app()->environment(['local', 'testing']),
        ];
    }

    public static function run(object $run): array
    {
        return [
            'visitor_id' => $run->visitor_id, 'funnel_version_id' => $run->funnel_version_id,
            'attribution' => $run->attribution, 'is_synthetic' => $run->is_synthetic,
        ];
    }
}
