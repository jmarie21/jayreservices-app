<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\SupportConversation;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = User::factory()->create(['role' => 'client']);
    $this->service = Service::factory()->create(['name' => 'Real Estate Basic Style']);
});

it('sends a chat message to admin when client marks a project for revision', function () {
    $project = Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'status' => 'sent_to_client',
        'project_name' => 'My Test Project',
    ]);

    $this->actingAs($this->client)
        ->put(route('projects.updateStatus', $project), ['status' => 'revision'])
        ->assertRedirect();

    $conversation = SupportConversation::where('client_id', $this->client->id)->first();
    expect($conversation)->not->toBeNull();

    $message = SupportMessage::where('sender_id', $this->client->id)->first();
    expect($message)->not->toBeNull();
    expect($message->body)->toContain('My Test Project');
    expect($message->body)->toContain('revision');
    expect($message->body)->toContain('Real Estate Basic Style');
});

it('uses existing conversation when client already has one', function () {
    $conversation = SupportConversation::create([
        'client_id' => $this->client->id,
    ]);

    $project = Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'status' => 'sent_to_client',
        'project_name' => 'Another Project',
    ]);

    $this->actingAs($this->client)
        ->put(route('projects.updateStatus', $project), ['status' => 'revision'])
        ->assertRedirect();

    expect(SupportConversation::where('client_id', $this->client->id)->count())->toBe(1);
    expect(SupportMessage::where('sender_id', $this->client->id)->count())->toBe(1);
});

it('does not send a chat message for non-revision status changes', function () {
    $project = Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'status' => 'for_qa',
        'project_name' => 'Some Project',
    ]);

    $this->actingAs($this->client)
        ->put(route('projects.updateStatus', $project), ['status' => 'done_qa'])
        ->assertRedirect();

    expect(SupportMessage::count())->toBe(0);
});
