<?php

namespace App\Funnel;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Configuration
{
    public static function legacy(array $config): bool
    {
        return ! isset($config['schema_version']);
    }

    public static function screen(array $config, string $id): ?array
    {
        return collect($config['steps'])->firstWhere('id', $id);
    }

    public static function entry(array $config): string
    {
        return self::legacy($config) ? 'memory' : self::enabledTarget($config, $config['entry']);
    }

    public static function enabledTarget(array $config, string $id): string
    {
        while ($id !== 'done' && ! self::screen($config, $id)['enabled']) {
            $id = self::screen($config, $id)['next'];
        }

        return $id;
    }

    public static function next(array $config, array $screen, array $answer): string
    {
        if (self::legacy($config)) {
            return 'result';
        }
        $target = $screen['next'];
        if (isset($screen['branch'])) {
            $expected = $screen['pattern'];
            sort($expected);
            if ($answer['cells'] !== $expected) {
                $target = $screen['branch']['incorrect'];
            }
        }

        return self::enabledTarget($config, $target);
    }

    public static function validate(array $config): void
    {
        $types = ['onboarding', 'tutorial', 'explanation', 'visual_memory', 'pattern', 'logic', 'result', 'boundary'];
        Validator::make($config, [
            'schema_version' => ['required', Rule::in([2])],
            'entry' => ['required', 'string'],
            'landing' => ['required', 'array:eyebrow,title,description,cta'],
            'landing.eyebrow' => ['required', 'string', 'max:100'],
            'landing.title' => ['required', 'string', 'max:120'],
            'landing.description' => ['required', 'string', 'max:500'],
            'landing.cta' => ['required', 'string', 'max:60'],
            'result' => ['required', 'array:title,disclaimer'],
            'result.title' => ['required', 'string', 'max:120'],
            'result.disclaimer' => ['required', Rule::in(['Illustrative demo values — not calculated from your answers.'])],
            'steps' => ['required', 'array', 'list', 'min:6', 'max:24'],
            'steps.*' => ['required', 'array:id,type,title,description,enabled,variant,next,branch,grid_size,pattern,reveal_seconds,sequence,options,correct_choice,cards,correct_order,rules'],
            'steps.*.id' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,39}$/', 'distinct:strict', Rule::notIn(['done', 'landing'])],
            'steps.*.type' => ['required', Rule::in($types)],
            'steps.*.title' => ['required', 'string', 'max:120'],
            'steps.*.description' => ['required', 'string', 'max:600'],
            'steps.*.enabled' => ['required', 'boolean'],
            'steps.*.variant' => ['required', Rule::in(['standard', 'spotlight'])],
            'steps.*.next' => ['required', 'string'],
        ])->validate();
        $fail = fn (string $message) => throw ValidationException::withMessages(['configuration' => $message]);
        if ($config['schema_version'] !== 2) { $fail('schema_version must be the integer 2.'); }
        if (array_diff(array_keys($config), ['schema_version', 'entry', 'landing', 'result', 'steps'])) { $fail('Unknown configuration fields.'); }
        $screens = array_column($config['steps'], null, 'id');
        if (! isset($screens[$config['entry']])) {
            $fail('The entry must name an existing screen.');
        }
        foreach ($screens as $screen) {
            $type = $screen['type'];
            if (! is_bool($screen['enabled'])) { $fail('enabled must be a JSON boolean.'); }
            $parameters = match ($type) {
                'visual_memory', 'explanation' => ['grid_size', 'pattern', 'reveal_seconds'],
                'tutorial' => ['grid_size', 'pattern', 'reveal_seconds', 'branch'],
                'pattern' => ['sequence', 'options', 'correct_choice'],
                'logic' => ['cards', 'rules', 'correct_order'],
                default => [],
            };
            if (array_diff(array_keys($screen), ['id', 'type', 'title', 'description', 'enabled', 'variant', 'next', ...$parameters])) {
                $fail('Screen parameters must match its type.');
            }
            if (isset($screen['branch']) && $type !== 'tutorial') {
                $fail('Only the tutorial supports an incorrect-answer branch.');
            }
            if (in_array($type, ['visual_memory', 'tutorial', 'explanation'])) {
                Validator::make($screen, [
                    'grid_size' => ['required', 'integer', 'min:3', 'max:4'],
                    'pattern' => ['required', 'array', 'list', 'min:1', 'max:16'],
                    'pattern.*' => ['required', 'integer', 'distinct:strict', 'min:0', 'max:'.(($screen['grid_size'] ?? 0) ** 2 - 1)],
                    'reveal_seconds' => ['required', 'integer', 'min:2', 'max:15'],
                ])->validate();
                if (! is_int($screen['grid_size']) || ! is_int($screen['reveal_seconds']) || array_filter($screen['pattern'], fn ($cell) => ! is_int($cell))) {
                    $fail('Memory parameters must use JSON integers.');
                }
            }
            if ($type === 'tutorial') {
                Validator::make($screen, ['branch' => ['required', 'array:incorrect'], 'branch.incorrect' => ['required', 'string']])->validate();
            }
            if ($type === 'pattern') {
                Validator::make($screen, [
                    'sequence' => ['required', 'array', 'list', 'min:3', 'max:6'], 'sequence.*' => ['required', 'string', 'max:12'],
                    'options' => ['required', 'array', 'list', 'min:2', 'max:4'], 'options.*' => ['array:id,symbol,label'],
                    'options.*.id' => ['required', 'string', 'max:30', 'distinct:strict'],
                    'options.*.symbol' => ['required', 'string', 'max:12'], 'options.*.label' => ['required', 'string', 'max:60'],
                    'correct_choice' => ['required', 'string', Rule::in(array_column($screen['options'] ?? [], 'id'))],
                ])->validate();
            }
            if ($type === 'logic') {
                Validator::make($screen, [
                    'cards' => ['required', 'array', 'list', 'min:3', 'max:5'], 'cards.*' => ['array:id,symbol,label'],
                    'cards.*.id' => ['required', 'string', 'max:30', 'distinct:strict'],
                    'cards.*.symbol' => ['required', 'string', 'max:12'], 'cards.*.label' => ['required', 'string', 'max:60'],
                    'correct_order' => ['required', 'array', 'list', 'size:'.count($screen['cards'] ?? [])],
                    'correct_order.*' => ['required', 'string', 'distinct:strict', Rule::in(array_column($screen['cards'] ?? [], 'id'))],
                    'rules' => ['required', 'array', 'list', 'min:1', 'max:4'], 'rules.*' => ['required', 'string', 'max:180'],
                ])->validate();
            }
            foreach ([$screen['next'], ...array_values($screen['branch'] ?? [])] as $target) {
                if ($target !== 'done' && ! isset($screens[$target])) {
                    $fail('Every transition must name an existing screen or done.');
                }
                if (($target === 'done') !== ($type === 'boundary')) {
                    $fail('Only the boundary can finish the demo, and it must finish at done.');
                }
            }
        }
        // Validate every edge, including disabled screens, before publishing anything.
        $visited = [];
        $visiting = [];
        $walk = function ($id) use (&$walk, &$visited, &$visiting, $screens, $fail) {
            if ($id === 'done' || isset($visited[$id])) { return; }
            if (isset($visiting[$id])) { $fail('Progression must not contain cycles.'); }
            $visiting[$id] = true;
            foreach ([$screens[$id]['next'], ...array_values($screens[$id]['branch'] ?? [])] as $target) { $walk($target); }
            unset($visiting[$id]);
            $visited[$id] = true;
        };
        foreach (array_keys($screens) as $id) { $walk($id); }
        foreach (['onboarding', 'tutorial', 'result', 'boundary'] as $type) {
            if (count(array_filter($screens, fn ($s) => $s['type'] === $type && $s['enabled'])) !== 1) {
                $fail("Exactly one enabled {$type} screen is required.");
            }
        }
        $tutorials = array_filter($screens, fn ($s) => $s['type'] === 'tutorial');
        if (count($tutorials) !== 1) { $fail('One tutorial branch is supported.'); }
        $tutorial = reset($tutorials);
        $guide = $screens[$tutorial['branch']['incorrect']];
        if (! $guide['enabled'] || $guide['type'] !== 'explanation' || self::enabledTarget($config, $guide['next']) !== self::enabledTarget($config, $tutorial['next'])) {
            $fail('The incorrect-answer explanation must rejoin the default tutorial path.');
        }
        // Every possible active path contains the complete experience in a valid order.
        $paths = function ($id, $types = [], $ids = []) use (&$paths, $config, $screens, $fail) {
            $id = self::enabledTarget($config, $id);
            if ($id === 'done') {
                foreach (['onboarding', 'tutorial', 'visual_memory', 'pattern', 'logic', 'result', 'boundary'] as $type) {
                    if (! in_array($type, $types)) { $fail("Every path must include {$type}."); }
                }
                return $ids;
            }
            $screen = $screens[$id];
            if (in_array('result', $types) && $screen['type'] !== 'boundary') { $fail('Result must lead directly to the boundary.'); }
            if ($screen['type'] === 'boundary' && end($types) !== 'result') { $fail('The boundary must follow the result.'); }
            if (in_array($screen['type'], ['onboarding', 'tutorial']) && array_intersect($types, ['visual_memory', 'pattern', 'logic'])) { $fail('Onboarding and tutorial must precede challenges.'); }
            $all = [$id];
            foreach ([$screen['next'], ...array_values($screen['branch'] ?? [])] as $target) {
                $all = array_merge($all, $paths($target, [...$types, $screen['type']], [...$ids, $id]));
            }
            return $all;
        };
        $reachable = $paths($config['entry']);
        foreach ($screens as $screen) {
            if ($screen['enabled'] && ! in_array($screen['id'], $reachable)) { $fail('Enabled screens must be reachable from entry.'); }
        }
    }
}
