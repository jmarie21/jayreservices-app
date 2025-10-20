<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ClientCommentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $comment;
    protected $project;

    /**
     * Create a new notification instance.
     */
    public function __construct(ProjectComment $comment, Project $project)
    {
        $this->comment = $comment;
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

        Log::info('Client Comment Notification', [
            'recipient' => $notifiable->name,
            'role' => $notifiable->role,
            'project' => $this->project->project_name,
            'client' => $this->comment->user->name,
        ]);

        $commentPreview = $this->comment->body 
            ? (strlen($this->comment->body) > 50 
                ? substr($this->comment->body, 0, 50) . '...' 
                : $this->comment->body)
            : '[Image attached]';

        return [
            'project_id' => $this->project->id,
            'comment_id' => $this->comment->id,
            'project_name' => $this->project->project_name,
            'client_name' => $this->comment->user->name ?? 'Client',
            'comment_preview' => $commentPreview,
            'message' => "<strong>{$this->comment->user->name}</strong> commented on project <strong>'{$this->project->project_name}'</strong>: {$commentPreview}",
            'type' => 'client_comment',
            'route_name' => $routeName,
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ];
    }
}