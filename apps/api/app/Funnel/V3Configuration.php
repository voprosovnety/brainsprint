<?php

namespace App\Funnel;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class V3Configuration
{
    public static function validate(array $config): void
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['configuration' => $message]);
        $base = ['id', 'type', 'title', 'description', 'enabled', 'variant', 'next'];
        $parameters = [
            'word_intro' => ['word', 'cta'],
            'single_choice' => ['options', 'presentation', 'asset', 'asset_alt', 'role'],
            'scale' => ['options'],
            'timed_reveal' => ['digits', 'reveal_seconds'],
            'interstitial' => ['cta', 'art'],
            'confirmation' => ['cta'],
            'processing' => ['prompts', 'stages', 'duration_seconds'],
            'email' => ['sample_email', 'cta'],
            'paywall' => ['offer_id'],
            'upsell' => ['offer_id', 'decline_next'],
            'final' => ['guide'],
        ];
        if (array_diff(array_keys($config), ['schema_version', 'entry', 'landing', 'result', 'offer', 'steps'])) {
            $fail('Unknown configuration fields.');
        }
        $asset = ['string', 'max:120', 'regex:~^/v3/[a-z0-9-]+\.svg$~'];
        Validator::make($config, [
            'schema_version' => ['required', Rule::in([3])],
            'entry' => ['required', 'string', 'max:40'],
            'landing' => ['required', 'array:eyebrow,title,description,cta'],
            'landing.eyebrow' => ['required', 'string', 'max:100'],
            'landing.title' => ['required', 'string', 'max:160'],
            'landing.description' => ['required', 'string', 'max:600'],
            'landing.cta' => ['required', 'string', 'max:60'],
            'result' => ['required', 'array:title,disclaimer'],
            'result.title' => ['required', 'string', 'max:160'],
            'result.disclaimer' => ['required', Rule::in(['Illustrative sample content, independent of answer correctness.'])],
            'offer' => ['required', 'array:id,comparison_cents,intro_cents,intro_days,renewal_cents,renewal_weeks,countdown_seconds,guide_id,guide_comparison_cents,guide_cents'],
            'offer.id' => ['required', Rule::in(['report-intro-v3'])],
            'offer.guide_id' => ['required', Rule::in(['guide-v3'])],
            'steps' => ['required', 'array', 'list', 'size:58'],
            'steps.*' => ['required', 'array'],
            'steps.*.id' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,39}$/', 'distinct:strict', Rule::notIn(['done', 'landing'])],
            'steps.*.type' => ['required', Rule::in(array_keys($parameters))],
            'steps.*.title' => ['required', 'string', 'max:180'],
            'steps.*.description' => ['required', 'string', 'max:800'],
            'steps.*.enabled' => ['required', 'boolean'],
            'steps.*.variant' => ['required', Rule::in(['standard', 'spotlight'])],
            'steps.*.next' => ['required', 'string', 'max:40'],
        ])->validate();
        foreach (['comparison_cents' => 999, 'intro_cents' => 99, 'intro_days' => 3, 'renewal_cents' => 3999, 'renewal_weeks' => 4, 'countdown_seconds' => 300, 'guide_comparison_cents' => 2700, 'guide_cents' => 100] as $key => $value) {
            if (($config['offer'][$key] ?? null) !== $value) { $fail('V3 offer values must match the bounded mock offer.'); }
        }
        $screens = array_column($config['steps'], null, 'id');
        if (! isset($screens[$config['entry']]) || $screens[$config['entry']]['type'] !== 'word_intro') {
            $fail('V3 must start with the word-memory introduction.');
        }
        foreach ($screens as $screen) {
            $type = $screen['type'];
            if ($screen['enabled'] !== true) { $fail('Every v3 screen is required.'); }
            if (array_diff(array_keys($screen), [...$base, ...$parameters[$type]])) { $fail('Screen parameters must match its type.'); }
            $rules = match ($type) {
                'word_intro' => ['word' => ['required', 'string', 'max:40'], 'cta' => ['required', 'string', 'max:60']],
                'single_choice', 'scale' => [
                    'options' => ['required', 'array', 'list', 'min:2', 'max:8'],
                    'options.*' => ['required', 'array:id,label,asset'],
                    'options.*.id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,30}$/', 'distinct:strict'],
                    'options.*.label' => ['required', 'string', 'max:180'],
                    'options.*.asset' => ['sometimes', ...$asset],
                    'presentation' => ['sometimes', Rule::in(['image', 'objects'])],
                    'asset' => ['sometimes', ...$asset],
                    'asset_alt' => ['sometimes', 'string', 'max:240'],
                    'role' => ['sometimes', Rule::in(['demographic'])],
                ],
                'timed_reveal' => ['digits' => ['required', 'string', 'regex:/^[0-9]{5,7}$/'], 'reveal_seconds' => ['required', 'integer', 'min:2', 'max:15']],
                'interstitial' => ['cta' => ['required', 'string', 'max:60'], 'art' => ['sometimes', ...$asset]],
                'confirmation' => ['cta' => ['required', 'string', 'max:60']],
                'processing' => [
                    'prompts' => ['present', 'array', 'list', 'max:2'],
                    'prompts.*' => ['required', 'array:id,title'],
                    'prompts.*.id' => ['required', 'distinct:strict', Rule::in(['focus', 'practice'])],
                    'prompts.*.title' => ['required', 'string', 'max:180'],
                    'stages' => ['required', 'array', 'list', 'min:1', 'max:5'],
                    'stages.*' => ['required', 'string', 'max:100'],
                    'duration_seconds' => ['required', 'integer', 'min:1', 'max:30'],
                ],
                'email' => ['sample_email' => ['required', Rule::in(['reader@example.com'])], 'cta' => ['required', 'string', 'max:60']],
                'paywall' => ['offer_id' => ['required', Rule::in([$config['offer']['id']])]],
                'upsell' => ['offer_id' => ['required', Rule::in([$config['offer']['guide_id']])], 'decline_next' => ['required', 'string', 'max:40']],
                'final' => ['guide' => ['required', 'boolean']],
            };
            Validator::make($screen, $rules)->validate();
            foreach (['reveal_seconds', 'duration_seconds'] as $integer) {
                if (isset($screen[$integer]) && ! is_int($screen[$integer])) { $fail('Durations must be JSON integers.'); }
            }
            if ($type === 'final' && ! is_bool($screen['guide'])) { $fail('guide must be a JSON boolean.'); }
            if (($screen['presentation'] ?? null) === 'image' && (! isset($screen['asset'], $screen['asset_alt']) || count(array_filter($screen['options'], fn ($o) => isset($o['asset']))) !== count($screen['options']))) {
                $fail('Image questions require a local diagram, accessible description and every option asset.');
            }
            if ($type === 'processing' && $screen['prompts'] !== [] && array_column($screen['prompts'], 'id') !== ['focus', 'practice']) {
                $fail('Preparation prompts must be the fixed focus and practice pair.');
            }
            foreach ([$screen['next'], ...($type === 'upsell' ? [$screen['decline_next']] : [])] as $target) {
                if ($target !== 'done' && ! isset($screens[$target])) { $fail('Every transition must name an existing screen or done.'); }
                if (($target === 'done') !== ($type === 'final')) { $fail('Only final screens may finish, and both must lead to done.'); }
            }
        }
        foreach (['word_intro' => 1, 'confirmation' => 1, 'processing' => 2, 'email' => 1, 'paywall' => 1, 'upsell' => 1, 'final' => 2, 'timed_reveal' => 3, 'interstitial' => 5] as $type => $count) {
            if (count(array_filter($screens, fn ($s) => $s['type'] === $type)) !== $count) { $fail("V3 requires {$count} {$type} screens."); }
        }
        if (count(array_filter($screens, fn ($s) => in_array($s['type'], ['single_choice', 'scale']))) !== 41 || count(array_filter($screens, fn ($s) => ($s['role'] ?? null) === 'demographic')) !== 2) {
            $fail('V3 requires 39 quiz responses and two demographic responses.');
        }
        $visited = [];
        $walk = function (string $id, array $path = []) use (&$walk, &$visited, $screens, $fail): void {
            if ($id === 'done') {
                $types = array_values(array_map(fn ($screen) => $screen['type'], $path));
                if (array_slice($types, -8) !== ['confirmation', 'interstitial', 'processing', 'email', 'processing', 'paywall', 'upsell', 'final']) {
                    $fail('Every path must preserve confirmation, preparation, offer, upsell and final order.');
                }
                $tail = array_values(array_slice($path, -8));
                if (count($tail[2]['prompts']) !== 2 || $tail[4]['prompts'] !== []) {
                    $fail('Only report preparation must collect the fixed preference prompts.');
                }
                return;
            }
            if (isset($path[$id])) { $fail('Progression must not contain cycles.'); }
            $screen = $screens[$id];
            $visited[$id] = true;
            $path[$id] = $screen;
            $walk($screen['next'], $path);
            if ($screen['type'] === 'upsell') {
                if ($screens[$screen['next']]['type'] !== 'final' || $screens[$screen['decline_next']]['type'] !== 'final' || $screens[$screen['next']]['guide'] !== true || $screens[$screen['decline_next']]['guide'] !== false) {
                    $fail('Upsell decisions must lead to the matching final composition.');
                }
                $walk($screen['decline_next'], $path);
            }
        };
        $walk($config['entry']);
        if (count($visited) !== count($screens)) { $fail('Every screen must be reachable from entry.'); }
    }
}
