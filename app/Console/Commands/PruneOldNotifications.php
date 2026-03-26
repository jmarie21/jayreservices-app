<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneOldNotifications extends Command
{
    /**
     * @var string
     */
    protected $signature = 'notifications:prune {--days=10 : Number of days to retain notifications}';

    /**
     * @var string
     */
    protected $description = 'Delete notifications older than the specified number of days';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = DatabaseNotification::where('created_at', '<', now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} notification(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
