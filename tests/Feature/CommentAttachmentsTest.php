<?php

use App\Models\Project;
use App\Models\ProjectCommentAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    $this->client = User::factory()->create(['role' => 'client']);
    $this->project = Project::factory()->create(['client_id' => $this->client->id]);
});

it('stores an image attachment', function () {
    $image = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(500);

    $response = $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'Here is a screenshot',
            'attachments' => [$image],
        ]);

    $response->assertRedirect();

    $attachment = ProjectCommentAttachment::first();
    expect($attachment)->not->toBeNull();
    expect($attachment->mime_type)->toStartWith('image/');
});

it('stores a video attachment', function () {
    $video = UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4');

    $response = $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'Check this clip',
            'attachments' => [$video],
        ]);

    $response->assertRedirect();

    $attachment = ProjectCommentAttachment::first();
    expect($attachment)->not->toBeNull();
    expect($attachment->mime_type)->toBe('video/mp4');
});

it('rejects an image exceeding 5 MB', function () {
    $image = UploadedFile::fake()->image('huge.jpg')->size(6000);

    $response = $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'Too big image',
            'attachments' => [$image],
        ]);

    $response->assertSessionHasErrors('attachments.0');
    expect(ProjectCommentAttachment::count())->toBe(0);
});

it('rejects a video exceeding 25 MB', function () {
    $video = UploadedFile::fake()->create('huge.mp4', 26000, 'video/mp4');

    $response = $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'Too big video',
            'attachments' => [$video],
        ]);

    $response->assertSessionHasErrors('attachments.0');
    expect(ProjectCommentAttachment::count())->toBe(0);
});

it('rejects more than 3 attachments', function () {
    $files = [
        UploadedFile::fake()->image('a.jpg', 100, 100)->size(100),
        UploadedFile::fake()->image('b.jpg', 100, 100)->size(100),
        UploadedFile::fake()->image('c.jpg', 100, 100)->size(100),
        UploadedFile::fake()->create('d.mp4', 500, 'video/mp4'),
    ];

    $response = $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'Too many',
            'attachments' => $files,
        ]);

    $response->assertSessionHasErrors('attachments');
    expect(ProjectCommentAttachment::count())->toBe(0);
});

it('accepts a mixed upload of 2 images and 1 video', function () {
    $files = [
        UploadedFile::fake()->image('a.jpg', 100, 100)->size(300),
        UploadedFile::fake()->image('b.png', 100, 100)->size(300),
        UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4'),
    ];

    $response = $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'Mixed media',
            'attachments' => $files,
        ]);

    $response->assertRedirect();
    expect(ProjectCommentAttachment::count())->toBe(3);
});

it('rejects unsupported file types', function () {
    $pdf = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'A pdf',
            'attachments' => [$pdf],
        ]);

    $response->assertSessionHasErrors('attachments.0');
    expect(ProjectCommentAttachment::count())->toBe(0);
});

it('preserves a video attachment during update', function () {
    $video = UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4');

    $this->actingAs($this->client)
        ->post(route('projects.comments.store', $this->project), [
            'body' => 'Original',
            'attachments' => [$video],
        ]);

    $comment = $this->project->comments()->first();
    $attachment = $comment->attachments()->first();

    $response = $this->actingAs($this->client)
        ->put(route('comments.update', $comment), [
            'body' => 'Updated text',
            'keep_attachment_ids' => [$attachment->id],
        ]);

    $response->assertRedirect();
    expect(ProjectCommentAttachment::count())->toBe(1);
    expect($comment->fresh()->body)->toBe('Updated text');
});
