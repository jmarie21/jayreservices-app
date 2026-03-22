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
    protected $description = 'Auto-unassign editors from in_progress projects that exceed their service tier deadline';

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
            $project->update([
                'status' => 'overdue',
                'editor_id' => null,
                'in_progress_since' => null,
            ]);

            $admins->each(fn (User $admin) => $admin->notify(new ProjectStalledNotification($project)));

            $this->info("Overdue project: {$project->project_name} (ID: {$project->id})");
        }

        $this->info("Total overdue projects: {$stalled->count()}");

        return self::SUCCESS;
    }
}
