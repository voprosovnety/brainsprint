<?php

namespace App\Funnel;

class V3State
{
    /** Private persisted envelope. Never expose this array as a consumer result. */
    public static function advance(array $configuration, array $screen, array $answer, string $next, ?array $envelope): array
    {
        $envelope ??= ['report' => null, 'commerce' => ['deadline' => null, 'unlocked' => false, 'upsell' => null]];
        if ($screen['type'] === 'confirmation' && $envelope['report'] === null) {
            $envelope['report'] = self::report($configuration);
        }
        if ((Configuration::screen($configuration, $next)['type'] ?? null) === 'paywall' && $envelope['commerce']['deadline'] === null) {
            $envelope['commerce']['deadline'] = now()->utc()->addSeconds($configuration['offer']['countdown_seconds'])->toIso8601String();
        }
        if ($screen['type'] === 'paywall') {
            $envelope['commerce']['unlocked'] = true;
        }
        if ($screen['type'] === 'upsell') {
            $envelope['commerce']['upsell'] = $answer['decision'];
        }

        return $envelope;
    }

    public static function milestones(array $screen, string $next, array $configuration): array
    {
        return match (true) {
            $screen['type'] === 'confirmation' => ['quiz_confirmed_v3'],
            $screen['type'] === 'processing' && (Configuration::screen($configuration, $next)['type'] ?? null) === 'paywall' => ['processing_completed_v3'],
            $screen['type'] === 'paywall' => ['mock_unlocked_v3'],
            $screen['type'] === 'upsell' => ['upsell_decided_v3'],
            $next === 'done' => ['funnel_completed_v3'],
            default => [],
        };
    }

    public static function publicState(?array $envelope): array
    {
        $commerce = $envelope['commerce'] ?? ['deadline' => null, 'unlocked' => false, 'upsell' => null];
        $report = $envelope['report'] ?? null;

        return [
            'teaser' => $report ? ['title' => $report['title'], 'summary' => $report['summary'], 'sections' => array_column($report['sections'], 'title')] : null,
            'result' => $commerce['unlocked'] ? $report : null,
            'commerce' => $commerce,
        ];
    }

    private static function report(array $configuration): array
    {
        $introductions = [
            'Explore three ways of approaching a new problem: holding a detail in mind, spotting a relationship, and testing a possible explanation.',
            'A thoughtful approach can begin with a small observation. This sample profile explores memory, patterns and reasoning through everyday practice.',
            'Make space for a fresh perspective. This sample profile connects memory, pattern spotting and careful reasoning with practical ideas to try.',
        ];

        return [
            'kind' => 'illustrative_sample', 'title' => $configuration['result']['title'],
            'summary' => $introductions[random_int(0, count($introductions) - 1)],
            'disclaimer' => $configuration['result']['disclaimer'],
            'sections' => [
                [
                    'title' => 'Memory and attention',
                    'body' => 'Memory tasks invite you to hold a small detail in mind while other information competes for attention. Grouping details into meaningful chunks is one useful strategy to explore.',
                    'practice' => 'Choose five ordinary objects. Look at them for twenty seconds, cover them, and name what you remember. Try grouping them into a short story and compare the experience.',
                ],
                [
                    'title' => 'Patterns and perspective',
                    'body' => 'A visual puzzle can change in more than one way: position, rotation, quantity or fill. Separating these features may make an unfamiliar pattern easier to inspect.',
                    'practice' => 'Sketch a row of four shapes that follows two simple rules. Explain each rule separately, then create one alternative ending that follows both.',
                ],
                [
                    'title' => 'Reasoning and reflection',
                    'body' => 'A careful explanation connects what is known to what follows. Looking for a counterexample and naming an assumption can be useful when an answer feels obvious.',
                    'practice' => 'Take a small everyday decision. Write down the facts, one assumption, and an alternative explanation. Notice which extra piece of information would help you choose.',
                ],
                [
                    'title' => 'A small daily practice',
                    'body' => 'A repeatable routine can make curiosity easier to revisit. Rotate a memory exercise, a visual pattern and a short reasoning question. Focus on describing your approach instead of chasing a score.',
                    'practice' => 'Set aside five quiet minutes tomorrow. Try one exercise, note the strategy you used, and choose one different approach for your next session.',
                ],
            ],
        ];
    }
}
