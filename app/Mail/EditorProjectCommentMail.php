<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EditorProjectCommentMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public ProjectComment $comment;

    public Project $project;

    public string $commenterLabel;

    /**
     * Create a new message instance.
     */
    public function __construct(ProjectComment $comment, Project $project)
    {
        $this->comment = $comment;
        $this->project = $project;
        $this->commenterLabel = match (strtolower((string) $comment->user?->role)) {
            'admin' => 'Admin',
            'editor' => 'Editor',
            default => 'Team',
        };
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New comment on your project: '.$this->project->project_name,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.editor_project_comment',
            with: [
                'project' => $this->project,
                'comment' => $this->comment,
                'commenterLabel' => $this->commenterLabel,
                'projectUrl' => route('projects', ['view' => $this->project->id]),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
