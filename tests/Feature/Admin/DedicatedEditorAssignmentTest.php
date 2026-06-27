<?php

use App\Models\ClientDedicatedEditor;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->basicService = Service::factory()->create(['name' => 'Real Estate Basic Style']);
    $this->deluxeService = Service::factory()->create(['name' => 'Real Estate Deluxe Style']);
});

it('exposes the client dedicated editor rules on the all projects page', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $serviceEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);
    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => $this->basicService->id, 'editor_id' => $serviceEditor->id]);

    Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.all'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/AllProjects')
            ->has('projects.data.0.client.dedicated_editor_rules', 2)
        );
});

it('allows assigning the general dedicated editor to a project of any service', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $generalEditor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => $generalEditor->id]);
});

it('rejects an editor not in the general set when a project has no service override', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $otherEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $generalEditor->id,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $otherEditor->id,
        ])
        ->assertSessionHasErrors(['editor_id']);

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => $generalEditor->id]);
});

it('allows assigning the service-specific dedicated editor to a project of that service', function () {
    $serviceEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => $this->basicService->id, 'editor_id' => $serviceEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $serviceEditor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => $serviceEditor->id]);
});

it('combines the general and service-specific editors for a project of that service', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $serviceEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);
    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => $this->basicService->id, 'editor_id' => $serviceEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    // The general editor is still allowed even though this service also has its own override.
    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $generalEditor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => $generalEditor->id]);
});

it('does not let a service-specific override apply to a different service', function () {
    $serviceEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => $this->basicService->id, 'editor_id' => $serviceEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->deluxeService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $serviceEditor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => $serviceEditor->id]);
});

it('rejects an editor not in either set when both general and service rules exist', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $serviceEditor = User::factory()->create(['role' => 'editor']);
    $otherEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);
    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => $this->basicService->id, 'editor_id' => $serviceEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $otherEditor->id,
        ])
        ->assertSessionHasErrors(['editor_id']);

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => null]);
});

it('still allows unassigning a project even when the client has dedicated editors', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $generalEditor->id,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => null]);
});

it('does not restrict editor assignment for clients without any dedication rules', function () {
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

    $this->assertDatabaseHas('projects', ['id' => $project->id, 'editor_id' => $editor->id]);
});

it('allows a dedicated editor to keep working on other clients projects', function () {
    $dedicatedEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);
    $otherClient = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $dedicatedEditor->id]);

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

    $this->assertDatabaseHas('projects', ['id' => $otherProject->id, 'editor_id' => $dedicatedEditor->id]);
});
