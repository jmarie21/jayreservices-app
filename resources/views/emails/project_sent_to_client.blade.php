<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Project Has Been Delivered</title>
</head>
<body>
    <h2>🎉 Your project is ready!</h2>

    <p>Hi {{ $project->client->name ?? 'Client' }},</p>

    <p>Your project <strong>{{ $project->project_name }}</strong> has been completed

    @if($project->output_link)
        <p>You can download your final video here: 
            <a href="{{ $project->output_link }}">{{ $project->output_link }}</a>
        </p>
    @endif

    <p>If you have any feedback or requests, feel free to leave a comment on the website.</p>

    <hr>
    <p>Thank you for choosing our video editing services!</p>
</body>
</html>
