<?php

namespace App\Funnel;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Answer
{
    public static function validate(Request $request, array $screen): array
    {
        $strictInteger = function ($attribute, $value, $fail) {
            if (! is_int($value)) { $fail('Each cell must be an integer.'); }
        };
        $rules = match ($screen['type']) {
            'visual_memory', 'tutorial' => [
                'answer' => ['required', 'array:cells'],
                'answer.cells' => ['present', 'array', 'list', 'max:'.($screen['grid_size'] ** 2)],
                'answer.cells.*' => ['required', 'integer', $strictInteger, 'distinct:strict', Rule::in(range(0, $screen['grid_size'] ** 2 - 1))],
            ],
            'pattern' => [
                'answer' => ['required', 'array:choice'],
                'answer.choice' => ['required', 'string', Rule::in(array_column($screen['options'], 'id'))],
            ],
            'logic' => [
                'answer' => ['required', 'array:order'],
                'answer.order' => ['required', 'array', 'list', 'size:'.count($screen['cards'])],
                'answer.order.*' => ['required', 'string', 'distinct:strict', Rule::in(array_column($screen['cards'], 'id'))],
            ],
            default => ['answer' => ['required', 'array:acknowledged'], 'answer.acknowledged' => ['required', function ($attribute, $value, $fail) {
                if ($value !== true) { $fail('Acknowledgement must be true.'); }
            }]],
        };
        if (array_diff(array_keys($request->all()), ['answer'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['answer' => 'Only an answer may be submitted.']);
        }
        $answer = $request->validate($rules)['answer'];
        if (isset($answer['cells'])) { sort($answer['cells']); }

        return $answer;
    }
}
