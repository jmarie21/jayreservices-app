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

    <hr>
    <p>Thank you for choosing us!</p>
</body>
</html>
