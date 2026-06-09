<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\SupportMessage;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->client = User::factory()->create(['role' => 'client']);
    $this->service = Service::factory()->create(['name' => 'Real Estate Basic Style']);
});

it('does not send a chat message when client marks a project for revision', function () {
    $project = Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'status' => 'sent_to_client',
        'project_name' => 'My Test Project',
    ]);

    $this->actingAs($this->client)
        ->put(route('projects.updateStatus', $project), ['status' => 'revision'])
        ->assertRedirect();

    expect(SupportMessage::count())->toBe(0);
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
