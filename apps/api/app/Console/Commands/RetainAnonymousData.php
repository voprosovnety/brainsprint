<?php

namespace App\Console\Commands;

use App\Lifecycle\Retention;
use Illuminate\Console\Command;

class RetainAnonymousData extends Command
{
    protected $signature = 'privacy:retention {--execute : Delete the selected expired records} {--limit=500 : Maximum visitors and sessions per table (1–1000)}';

    protected $description = 'Preview anonymous data older than 30 days and expired sessions; explicitly opt into deletion';

    public function handle(Retention $retention): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be 1–1000.');

            return self::FAILURE;
        }
        $this->line(json_encode($retention->run((bool) $this->option('execute'), $limit), JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
