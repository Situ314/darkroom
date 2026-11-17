<?php

namespace App\Console\Commands;

use App\Services\Pixelforge\GenerationPoller;
use Illuminate\Console\Command;

class SyncGenerations extends Command
{
    protected $signature = 'pixelforge:sync {--older-than=60 : Only poll generations not synced in this many seconds}';

    protected $description = 'Poll Pixelforge for in-flight generations whose webhooks may have been missed';

    public function handle(GenerationPoller $poller): int
    {
        $synced = $poller->pollStale((int) $this->option('older-than'));

        $this->info("Polled {$synced} generation(s).");

        return self::SUCCESS;
    }
}
