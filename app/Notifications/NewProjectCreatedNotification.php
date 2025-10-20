<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class NewProjectCreatedNotification extends Notification implements ShouldQueue
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
        return ['database', 'broadcast'];
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

        Log::info('New Project Notification', [
            'user' => $notifiable->name,
            'role' => $notifiable->role,
            'route' => $routeName,
            'project' => $this->project->project_name,
        ]);

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'client_name' => $this->project->client->name ?? 'Unknown Client',
            'message' => "New project <strong>'{$this->project->project_name}'</strong> has been created by <strong>{$this->project->client->name}</strong>.",
            'type' => 'project_created',
            'route_name' => $routeName,
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'client_name' => $this->project->client->name ?? 'Unknown Client',
            'message' => "New project <strong>'{$this->project->project_name}'</strong> has been created by <strong>{$this->project->client->name}</strong>.",
            'type' => 'project_created',
            'route_name' => $notifiable->role === 'admin' ? 'projects.all' : 'editor.projects.index',
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
