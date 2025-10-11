<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Project Created</title>
</head>
<body>
    <h2>New Project Created</h2>

    <p><strong>Client:</strong> {{ $project->client->name }}</p>
    <p><strong>Project Name:</strong> {{ $project->project_name }}</p>
    <p><strong>Style:</strong> {{ ucfirst($project->style) }}</p>
    <p><strong>Service:</strong> {{ $project->service->name ?? 'N/A' }}</p>
    <p><strong>Total Price:</strong> ${{ number_format($project->total_price, 2) }}</p>
    <p><strong>File Link:</strong> <a href="{{ $project->file_link }}">{{ $project->file_link }}</a></p>
    <p><strong>Notes:</strong> {{ $project->notes ?? 'None' }}</p>

    <p>Visit the admin panel to view the full project details.</p>
</body>
</html>
