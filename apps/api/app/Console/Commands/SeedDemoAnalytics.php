<?php

namespace App\Console\Commands;

use App\Analytics\SyntheticScenario;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class SeedDemoAnalytics extends Command
{
    protected $signature = 'analytics:seed-demo {--date= : UTC anchor date (YYYY-MM-DD), defaults to today}';

    protected $description = 'Append an idempotent, clearly synthetic daily analytics scenario';

    public function handle(SyntheticScenario $scenario): int
    {
        $date = $this->option('date') ?: CarbonImmutable::now('UTC')->toDateString();
        $validator = Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d|before_or_equal:today']);
        if ($validator->fails()) {
            $this->error('Use a UTC date in the past or today (YYYY-MM-DD).');

            return self::FAILURE;
        }
        $scenario->seed(CarbonImmutable::parse($date, 'UTC'));
        $this->info('Synthetic scenario available. Existing scenario and consumer records were preserved.');

        return self::SUCCESS;
    }
}
