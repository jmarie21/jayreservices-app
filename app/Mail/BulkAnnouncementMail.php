<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BulkAnnouncementMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $announcementSubject;
    public string $announcementMessage;
    public string $recipientName;

    /**
     * Create a new message instance.
     */
    public function __construct(string $subject, string $message, string $recipientName = 'Valued Client')
    {
        $this->announcementSubject = $subject;
        $this->announcementMessage = $message;
        $this->recipientName = $recipientName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->announcementSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.bulk_announcement',
            with: [
                'subject' => $this->announcementSubject,
                'messageContent' => $this->announcementMessage,
                'recipientName' => $this->recipientName,
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
