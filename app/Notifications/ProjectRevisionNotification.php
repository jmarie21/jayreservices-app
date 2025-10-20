<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProjectRevisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $project;

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
        // Determine route based on user role
        $routeName = $notifiable->role === 'admin'
            ? 'projects.all'
            : 'editor.projects.index';

            Log::info('Notification route', [
        'user' => $notifiable->name,
        'role' => $notifiable->role,
        'route' => $routeName,
    ]);

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'message' => "Project <strong>'{$this->project->project_name}'</strong> has been marked for revision.",
            'type' => 'project_revision',
            'route_name' => $routeName,
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ];
    }

}
