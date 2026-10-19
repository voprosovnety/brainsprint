<?php

namespace App\Filament\Pages;

use App\Analytics\AggregateService;
use App\Analytics\Dataset;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Validator;

class Analytics extends Page
{
    protected string $view = 'filament.pages.analytics';

    protected static ?string $slug = 'analytics';

    protected static ?string $title = 'Funnel analytics';

    protected static ?int $navigationSort = -10;

    public string $from = '';

    public string $to = '';

    public string $dataset = 'real';

    public int $observationHours = 24;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->is_admin === true;
    }

    public function mount(): void
    {
        $this->from = CarbonImmutable::now('UTC')->subDays(7)->format('Y-m-d\TH:i');
        $this->to = CarbonImmutable::now('UTC')->format('Y-m-d\TH:i');
    }

    private function inputValidator()
    {
        $validator = Validator::make(['from' => $this->from, 'to' => $this->to, 'dataset' => $this->dataset, 'observationHours' => $this->observationHours], [
            'from' => ['required', 'date_format:Y-m-d\TH:i', 'before:to'],
            'to' => ['required', 'date_format:Y-m-d\TH:i', 'before_or_equal:'.CarbonImmutable::now('UTC')->format('Y-m-d\TH:i')],
            'dataset' => ['required', 'in:real,synthetic'], 'observationHours' => ['required', 'integer', 'min:1', 'max:168'],
        ]);
        $validator->after(function ($v) {
            if (! $v->errors()->isEmpty()) {
                return;
            }
            if (CarbonImmutable::parse($this->from, 'UTC')->diffInDays(CarbonImmutable::parse($this->to, 'UTC')) > 93) {
                $v->errors()->add('from', 'Choose a range of at most 93 days.');
            }
        });

        return $validator;
    }

    public function apply(): void
    {
        $this->inputValidator()->validate();
    }

    protected function getViewData(): array
    {
        if ($this->inputValidator()->fails()) {
            return ['metrics' => null];
        }

        return ['metrics' => app(AggregateService::class)->summarize(
            CarbonImmutable::parse($this->from, 'UTC'), CarbonImmutable::parse($this->to, 'UTC'),
            dataset: Dataset::from($this->dataset), observationHours: $this->observationHours,
        )];
    }
}
