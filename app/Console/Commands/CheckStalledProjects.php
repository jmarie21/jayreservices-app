<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectStalledNotification;
use Illuminate\Console\Command;

class CheckStalledProjects extends Command
{
    /**
     * @var string
     */
    protected $signature = 'projects:check-stalled';

    /**
     * @var string
     */
    protected $description = 'Notify admins when in_progress projects exceed their service tier deadline';

    public function handle(): int
    {
        $candidates = Project::with('service')
            ->where('status', 'in_progress')
            ->whereNotNull('in_progress_since')
            ->get();

        $stalled = $candidates->filter(function (Project $project): bool {
            $deadlineHours = $project->getStallDeadlineHours();

            return $project->in_progress_since->lte(now()->subHours($deadlineHours));
        });

        if ($stalled->isEmpty()) {
            $this->info('No stalled projects found.');

            return self::SUCCESS;
        }

        $admins = User::where('role', 'admin')->get();

        foreach ($stalled as $project) {
            $admins->each(fn (User $admin) => $admin->notify(new ProjectStalledNotification($project)));

            $this->info("Stalled project: {$project->project_name} (ID: {$project->id})");
        }

        $this->info("Total stalled projects: {$stalled->count()}");

        return self::SUCCESS;
    }
}
