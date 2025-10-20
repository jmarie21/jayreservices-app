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
        // Determine route based on recipient role
        $routeName = $notifiable->role === 'admin'
            ? 'projects.all'
            : 'editor.projects.index';

        $role = strtolower($this->comment->user->role ?? '');
        $originalName = $this->comment->user->name ?? 'User';

        // Determine display name based on both commenter and recipient roles
        if ($role === 'admin') {
            $commenterName = 'Admin';
        } elseif ($role === 'client') {
            // Admins see the actual client name; editors just see "Client"
            $commenterName = $notifiable->role === 'admin' ? $originalName : 'Client';
        } else {
            $commenterName = $originalName;
        }

        Log::info('Client Comment Notification', [
            'recipient' => $notifiable->name,
            'recipient_role' => $notifiable->role,
            'commenter_role' => $role,
            'displayed_as' => $commenterName,
            'project' => $this->project->project_name,
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
            'commenter_name' => $commenterName,
            'comment_preview' => $commentPreview,
            'message' => "<strong>{$commenterName}</strong> commented on project <strong>'{$this->project->project_name}'</strong>: {$commentPreview}",
            'type' => 'client_comment',
            'route_name' => $routeName,
            'route_params' => [
                'view' => $this->project->id,
            ],
            'created_at' => now()->toDateTimeString(),
        ];
    }


}