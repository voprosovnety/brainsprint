<?php

namespace App\Funnel;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Answer
{
    public static function validate(Request $request, array $screen): array
    {
        $rules = match ($screen['type']) {
            'single_choice', 'scale' => [
                'answer' => ['required', 'array:choice'],
                'answer.choice' => ['required', 'string', Rule::in(array_column($screen['options'], 'id'))],
            ],
            'processing' => empty($screen['prompts']) ? self::acknowledgement() : [
                'answer' => ['required', 'array:focus,practice'],
                'answer.focus' => ['required', 'string', Rule::in(['yes', 'no'])],
                'answer.practice' => ['required', 'string', Rule::in(['yes', 'no'])],
            ],
            'email' => [
                'answer' => ['required', 'array:email'],
                'answer.email' => ['present', 'nullable', 'string', 'email:rfc', 'max:254'],
            ],
            'paywall' => [
                'answer' => ['required', 'array:offer_id,acknowledged'],
                'answer.offer_id' => ['required', 'string', Rule::in([$screen['offer_id']])],
                'answer.acknowledged' => self::acknowledgement()['answer.acknowledged'],
            ],
            'upsell' => [
                'answer' => ['required', 'array:decision'],
                'answer.decision' => ['required', 'string', Rule::in(['accept', 'decline'])],
            ],
            default => self::acknowledgement(),
        };
        if (array_diff(array_keys($request->all()), ['answer'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['answer' => 'Only an answer may be submitted.']);
        }
        $answer = $request->validate($rules)['answer'];

        return self::canonical($answer);
    }

    public static function canonical(array $answer): array
    {
        ksort($answer);

        return $answer;
    }

    private static function acknowledgement(): array
    {
        return ['answer' => ['required', 'array:acknowledged'], 'answer.acknowledged' => ['required', function ($attribute, $value, $fail) {
            if ($value !== true) { $fail('Acknowledgement must be true.'); }
        }]];
    }
}
