<?php

use Illuminate\Support\Facades\Schedule;

// Fallback for missed webhooks. Webhooks remain the primary source of updates.
Schedule::command('pixelforge:sync')->everyMinute()->withoutOverlapping();
