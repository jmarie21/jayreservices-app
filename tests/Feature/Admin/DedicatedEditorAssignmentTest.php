<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->basicService = Service::factory()->create(['name' => 'Real Estate Basic Style']);
});

it('allows assigning the dedicated editor to a project', function () {
    $dedicatedEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client', 'dedicated_editor_id' => $dedicatedEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $dedicatedEditor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'editor_id' => $dedicatedEditor->id,
    ]);
});

it('rejects assigning a different editor when the client has a dedicated editor', function () {
    $dedicatedEditor = User::factory()->create(['role' => 'editor']);
    $otherEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client', 'dedicated_editor_id' => $dedicatedEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $dedicatedEditor->id,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $otherEditor->id,
        ])
        ->assertSessionHasErrors(['editor_id']);

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'editor_id' => $dedicatedEditor->id,
    ]);
});

it('still allows unassigning a project even when the client has a dedicated editor', function () {
    $dedicatedEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client', 'dedicated_editor_id' => $dedicatedEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $dedicatedEditor->id,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'editor_id' => null,
    ]);
});

it('does not restrict editor assignment for clients without a dedicated editor', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $editor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'editor_id' => $editor->id,
    ]);
});

it('allows the dedicated editor to keep working on other clients projects', function () {
    $dedicatedEditor = User::factory()->create(['role' => 'editor']);
    User::factory()->create(['role' => 'client', 'dedicated_editor_id' => $dedicatedEditor->id]);
    $otherClient = User::factory()->create(['role' => 'client']);

    $otherProject = Project::factory()->create([
        'client_id' => $otherClient->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $otherProject), [
            'editor_id' => $dedicatedEditor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id' => $otherProject->id,
        'editor_id' => $dedicatedEditor->id,
    ]);
});
