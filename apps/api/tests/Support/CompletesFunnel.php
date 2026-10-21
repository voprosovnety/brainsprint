<?php

namespace Tests\Support;

use App\Funnel\Configuration;

trait CompletesFunnel
{
    private function answer(array $screen, string $decision = 'accept'): array
    {
        return match ($screen['type']) {
            'single_choice', 'scale' => ['choice' => $screen['options'][0]['id']],
            'processing' => empty($screen['prompts']) ? ['acknowledged' => true] : ['focus' => 'yes', 'practice' => 'no'],
            'email' => ['email' => null],
            'paywall' => ['offer_id' => $screen['offer_id'], 'acknowledged' => true],
            'upsell' => ['decision' => $decision],
            default => ['acknowledged' => true],
        };
    }

    private function complete(array $run, string $step, array $answer)
    {
        return $this->postJson('/api/v1/runs/'.$run['id'].'/steps/'.$step.'/complete', ['answer' => $answer]);
    }

    private function advanceTo(array $run, string $target): array
    {
        while ($run['current_step'] !== $target) {
            $this->assertNotSame('done', $run['current_step'], 'Target must be on the current path.');
            $screen = Configuration::screen($run['configuration'], $run['current_step']);
            $run = $this->complete($run, $screen['id'], $this->answer($screen))->assertOk()->json();
        }

        return $run;
    }
}
