<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class ClientProjectCommentNotification extends Notification implements ShouldQueue
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
        $role = strtolower($this->comment->user->role ?? '');
        $originalName = $this->comment->user->name ?? 'User';

        // For clients, show "Admin" or "Editor" instead of the actual name
        if ($role === 'admin') {
            $commenterName = 'Admin';
        } elseif ($role === 'editor') {
            $commenterName = 'Editor';
        } else {
            $commenterName = $originalName;
        }

        $commentPreview = $this->comment->body
            ? (strlen($this->comment->body) > 50
                ? substr($this->comment->body, 0, 50) . '...'
                : $this->comment->body)
            : '[Image attached]';

        Log::info('Sending client project comment notification', [
            'project_id' => $this->project->id,
            'project_name' => $this->project->project_name,
            'comment_id' => $this->comment->id,
            'commenter_id' => $this->comment->user_id,
            'commenter_role' => $role,
            'displayed_as' => $commenterName,
            'client_id' => $notifiable->id,
            'client_name' => $notifiable->name,
        ]);

        return [
            'project_id' => $this->project->id,
            'comment_id' => $this->comment->id,
            'project_name' => $this->project->project_name,
            'commenter_name' => $commenterName,
            'comment_preview' => $commentPreview,
            'message' => "<strong>{$commenterName}</strong> commented on your project <strong>'{$this->project->project_name}'</strong>: {$commentPreview}",
            'type' => 'client_project_comment',
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
