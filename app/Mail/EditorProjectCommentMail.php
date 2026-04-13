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

    /**
     * Create a new message instance.
     */
    public function __construct(ProjectComment $comment, Project $project)
    {
        $this->comment = $comment;
        $this->project = $project;
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
