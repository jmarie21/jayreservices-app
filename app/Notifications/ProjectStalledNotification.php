<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProjectStalledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Project $project) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        Log::info('Project Stalled Notification', [
            'admin' => $notifiable->name,
            'project' => $this->project->project_name,
        ]);

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'message' => "Project <strong>'{$this->project->project_name}'</strong> was automatically unassigned — no progress after {$this->project->getStallDeadlineHours()} hours.",
            'type' => 'project_stalled',
            'route_name' => 'projects.all',
            'route_params' => [],
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
