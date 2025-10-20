<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ProjectStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $project;
    protected $status;
    protected $triggeredBy; // e.g. 'admin' or 'editor'

    public function __construct(Project $project, string $status, string $triggeredBy = 'system')
    {
        $this->project = $project;
        $this->status = strtolower($status);
        $this->triggeredBy = $triggeredBy;
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
        $editorName = $this->project->editor?->name ?? 'the assigned editor';

        return match ($this->status) {
            'for_revision' => "Project <strong>'{$projectName}'</strong> has been marked for revision by {$this->triggeredBy}.",
            'for_qa' => "Project <strong>'{$projectName}'</strong> has been submitted for QA by {$editorName}.",
            'done_qa' => "Project <strong>'{$projectName}'</strong> has been marked Done QA by {$this->triggeredBy}.",
            'revision_completed' => "Project <strong>'{$projectName}'</strong> revision has been completed by {$editorName}.",
            'sent_to_client' => "Project <strong>'{$projectName}'</strong> has been sent to the client by {$editorName}.",
            default => "Project <strong>'{$projectName}'</strong> status updated to <strong>{$this->status}</strong>.",
        };
    }

   
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $routeName = $notifiable->role === 'admin'
            ? 'projects.all'
            : 'editor.projects.index';

        $message = $this->getMessage();

        Log::info('Sending project status notification', [
            'project_id' => $this->project->id,
            'status' => $this->status,
            'to' => $notifiable->name,
            'role' => $notifiable->role,
        ]);

        return [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'message' => $message,
            'status' => $this->status,
            'type' => 'project_status_update',
            'route_name' => $routeName,
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ];
    }

}
