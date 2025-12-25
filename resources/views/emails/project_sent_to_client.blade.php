<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Project Has Been Delivered</title>
</head>
<body>
    <h2>Your project {{ $project->project_name }} is ready! 🎉</h2>

    <p>Hi {{ $project->client->name ?? 'Client' }},</p>

    <p>We're excited to inform you that your project is now complete!
        You can access the finished output links below:
    </p>

    @if($project->output_link && count($project->output_link) > 0)
        <div style="margin: 20px 0;">
            @foreach($project->output_link as $index => $link)
                <p>
                    🎬 <strong>Output {{ count($project->output_link) > 1 ? $index + 1 : '' }}:</strong> 
                    <a href="{{ $link }}" target="_blank" style="color: #1a73e8; text-decoration: underline;">
                        {{ $link }}
                    </a>
                </p>
            @endforeach
        </div>
    @endif

    <p>
        You can also view your project details and comments on our website:
    </p>

    <p>
        👉 
        <a 
            href="{{ config('app.url') }}" 
            target="_blank" 
            style="color: #1a73e8; text-decoration: none; font-weight: bold;"
        >
            View Project on Website
        </a>
    </p>

    <hr>
    <p>Thank you for choosing us!</p>
</body>
</html>
