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
        You can access the project link in the comment section within your project page on our website.
    </p>

    <p>
        👉 
        <a 
            href="{{ config('app.url') }}/projects?view={{ $project->id }}" 
            target="_blank" 
            style="color: #1a73e8; text-decoration: none; font-weight: bold;"
        >
            View Your Project
        </a>
    </p>

    <hr>
    <p>Thank you for choosing us!</p>
</body>
</html>
