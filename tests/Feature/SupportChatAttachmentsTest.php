<?php

use App\Models\SupportConversation;
use App\Models\SupportMessageAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = User::factory()->create(['role' => 'client']);
});

it('allows client to send a message with an image attachment', function () {
    $image = UploadedFile::fake()->image('screenshot.jpg', 100, 100)->size(500);

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'See this screenshot',
            'attachments' => [$image],
        ]);

    $response->assertCreated();

    $attachment = SupportMessageAttachment::first();
    expect($attachment)->not->toBeNull();
    expect($attachment->mime_type)->toStartWith('image/');
});

it('allows client to send a message with a video attachment', function () {
    $video = UploadedFile::fake()->create('clip.mp4', 5000, 'video/mp4');

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Watch this',
            'attachments' => [$video],
        ]);

    $response->assertCreated();

    $attachment = SupportMessageAttachment::first();
    expect($attachment)->not->toBeNull();
    expect($attachment->mime_type)->toBe('video/mp4');
});

it('allows client to send attachment-only message without body', function () {
    $image = UploadedFile::fake()->image('photo.png', 100, 100)->size(200);

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => '',
            'attachments' => [$image],
        ]);

    $response->assertCreated();
    expect(SupportMessageAttachment::count())->toBe(1);
});

it('rejects empty message with no body and no attachments', function () {
    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => '',
        ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors('body');
});

it('rejects client image exceeding 5 MB', function () {
    $image = UploadedFile::fake()->image('huge.jpg')->size(6000);

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Too big',
            'attachments' => [$image],
        ]);

    $response->assertUnprocessable();
    expect(SupportMessageAttachment::count())->toBe(0);
});

it('rejects client video exceeding 25 MB', function () {
    $video = UploadedFile::fake()->create('huge.mp4', 26000, 'video/mp4');

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Too big',
            'attachments' => [$video],
        ]);

    $response->assertUnprocessable();
    expect(SupportMessageAttachment::count())->toBe(0);
});

it('rejects more than 3 attachments from client', function () {
    $files = [
        UploadedFile::fake()->image('a.jpg', 100, 100)->size(100),
        UploadedFile::fake()->image('b.jpg', 100, 100)->size(100),
        UploadedFile::fake()->image('c.jpg', 100, 100)->size(100),
        UploadedFile::fake()->create('d.mp4', 500, 'video/mp4'),
    ];

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Too many',
            'attachments' => $files,
        ]);

    $response->assertUnprocessable();
    expect(SupportMessageAttachment::count())->toBe(0);
});

it('allows admin to send a message with attachments', function () {
    $conversation = SupportConversation::create([
        'client_id' => $this->client->id,
    ]);

    $image = UploadedFile::fake()->image('reply.png', 100, 100)->size(300);
    $video = UploadedFile::fake()->create('demo.mp4', 3000, 'video/mp4');

    $response = $this->actingAs($this->admin)
        ->postJson(route('admin.messages.messages.store', $conversation), [
            'body' => 'Here is the fix',
            'attachments' => [$image, $video],
        ]);

    $response->assertCreated();
    expect(SupportMessageAttachment::count())->toBe(2);
});

it('includes attachments in the message response payload', function () {
    $image = UploadedFile::fake()->image('screenshot.jpg', 100, 100)->size(500);

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'Check this',
            'attachments' => [$image],
        ]);

    $response->assertCreated();

    $messagePayload = $response->json('message');
    expect($messagePayload['attachments'])->toBeArray();
    expect($messagePayload['attachments'])->toHaveCount(1);
    expect($messagePayload['attachments'][0])->toHaveKeys(['id', 'url', 'mime_type', 'original_name']);
});

it('rejects unsupported file types in chat', function () {
    $pdf = UploadedFile::fake()->create('doc.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->client)
        ->postJson(route('support-chat.messages.store'), [
            'body' => 'A pdf',
            'attachments' => [$pdf],
        ]);

    $response->assertUnprocessable();
    expect(SupportMessageAttachment::count())->toBe(0);
});
