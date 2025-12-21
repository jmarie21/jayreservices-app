<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ClientProjectStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $project;
    protected $status;

    /**
     * Create a new notification instance.
     */
    public function __construct(Project $project, string $status)
    {
        $this->project = $project;
        $this->status = strtolower($status);
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
     * Build a dynamic message based on project status.
     */
    protected function getMessage(): string
    {
        $projectName = $this->project->project_name;

        return match ($this->status) {
            'sent_to_client' => "Great news! Your project <strong>'{$projectName}'</strong> is ready for review.",
            'revision' => "Your project <strong>'{$projectName}'</strong> has been marked for revision.",
            'revision_completed' => "The revision for your project <strong>'{$projectName}'</strong> has been completed.",
            default => "Your project <strong>'{$projectName}'</strong> status has been updated to <strong>{$this->status}</strong>.",
        };
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $message = $this->getMessage();

        Log::info('Sending client project status notification', [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'status' => $this->status,
            'client_id' => $notifiable->id,
            'client_name' => $notifiable->name,
            'client_email' => $notifiable->email,
        ]);

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'message' => $message,
            'status' => $this->status,
            'type' => 'client_project_status',
            'route_name' => 'projects',
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
