<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New comment on your project</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.6;">
    <h2 style="margin-bottom: 16px;">Editor left a new comment on {{ $project->project_name }}</h2>

    <p>Hi {{ $project->client->name ?? 'Client' }},</p>

    <p>
        There is a new update on your project. You can review the comment below and sign in to the client portal for the full conversation.
    </p>

    @if (filled(trim((string) $comment->body)))
        <div style="margin: 20px 0; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb;">
            <strong style="display: block; margin-bottom: 8px;">Comment</strong>
            <div style="white-space: pre-line;">{{ $comment->body }}</div>
        </div>
    @else
        <div style="margin: 20px 0; padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb;">
            <strong style="display: block; margin-bottom: 8px;">Comment</strong>
            <div>Editor added {{ $comment->attachments()->count() === 1 ? 'an attachment' : $comment->attachments()->count().' attachments' }} to the project comments.</div>
        </div>
    @endif

    <p>
        <a
            href="{{ $projectUrl }}"
            target="_blank"
            style="display: inline-block; padding: 10px 16px; border-radius: 6px; background: #111827; color: #ffffff; text-decoration: none; font-weight: 600;"
        >
            View Project Comments
        </a>
    </p>

    <p style="margin-top: 24px;">Thank you.</p>
</body>
</html>
