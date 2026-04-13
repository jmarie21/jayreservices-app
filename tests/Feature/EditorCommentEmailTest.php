<?php

use App\Mail\EditorProjectCommentMail;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

it('queues an email to the client notification addresses when an editor comments on a project', function () {
    Mail::fake();
    Notification::fake();

    $client = User::factory()->create([
        'role' => 'client',
        'email' => 'client@example.com',
        'additional_emails' => ['assistant@example.com', 'manager@example.com'],
    ]);
    $editor = User::factory()->create(['role' => 'editor']);
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $editor->id,
    ]);

    $response = $this->actingAs($editor)
        ->post(route('projects.comments.store', $project), [
            'body' => 'Please review the latest changes.',
        ]);

    $response->assertRedirect();

    Mail::assertQueued(EditorProjectCommentMail::class, function (EditorProjectCommentMail $mail) use ($client, $project) {
        return $mail->project->is($project)
            && $mail->comment->project_id === $project->id
            && $mail->hasTo($client->email)
            && $mail->hasTo('assistant@example.com')
            && $mail->hasTo('manager@example.com');
    });
});

it('does not queue the client comment email when the commenter is an admin', function () {
    Mail::fake();
    Notification::fake();

    $client = User::factory()->create([
        'role' => 'client',
        'email' => 'client@example.com',
        'additional_emails' => ['assistant@example.com'],
    ]);
    $admin = User::factory()->create(['role' => 'admin']);
    $project = Project::factory()->create([
        'client_id' => $client->id,
    ]);

    $response = $this->actingAs($admin)
        ->post(route('projects.comments.store', $project), [
            'body' => 'We updated the project notes for you.',
        ]);

    $response->assertRedirect();

    Mail::assertNothingQueued();
});

it('does not queue the client comment email when the commenter is a client', function () {
    Mail::fake();
    Notification::fake();

    $client = User::factory()->create([
        'role' => 'client',
        'email' => 'client@example.com',
        'additional_emails' => ['assistant@example.com'],
    ]);
    $project = Project::factory()->create([
        'client_id' => $client->id,
    ]);

    $response = $this->actingAs($client)
        ->post(route('projects.comments.store', $project), [
            'body' => 'Can we revise this section?',
        ]);

    $response->assertRedirect();

    Mail::assertNothingQueued();
});
