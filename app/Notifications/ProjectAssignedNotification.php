<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProjectAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $project;

    /**
     * Create a new notification instance.
     */
    public function __construct(Project $project)
    {
        $this->project = $project;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        Log::info('Project Assigned Notification', [
            'editor' => $notifiable->name,
            'project' => $this->project->project_name,
        ]);

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'client_name' => $this->project->client->name ?? 'Unknown Client',
            'service_name' => $this->project->service->name ?? 'N/A',
            'message' => "You have been assigned to project <strong>'{$this->project->project_name}'</strong>.",
            'type' => 'project_assigned',
            'route_name' => 'editor.projects.index',
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
