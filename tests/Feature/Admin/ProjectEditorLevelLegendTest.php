<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->basicService = Service::factory()->create(['name' => 'Real Estate Basic Style']);
});

it('exposes editor levels and the client recommended level on the all projects page', function () {
    $seniorEditor = User::factory()->create(['role' => 'editor', 'editor_level' => 'senior']);
    $client = User::factory()->create(['role' => 'client', 'recommended_editor_level' => 'senior']);

    Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $seniorEditor->id,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.all'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/AllProjects')
            ->where('editors.0.editor_level', 'senior')
            ->where('clients.0.recommended_editor_level', 'senior')
            ->where('projects.data.0.client.recommended_editor_level', 'senior')
        );
});

it('exposes the client recommended level and editor levels on the client projects page', function () {
    $juniorEditor = User::factory()->create(['role' => 'editor', 'editor_level' => 'junior']);
    $client = User::factory()->create(['role' => 'client', 'recommended_editor_level' => 'senior']);

    $this->actingAs($this->admin)
        ->get(route('client.projects', $client))
        ->assertInertia(fn ($page) => $page
            ->component('admin/ClientProjects')
            ->where('client.recommended_editor_level', 'senior')
            ->where('editors.0.editor_level', 'junior')
        );
});

it('still allows assigning any editor to a project regardless of the level legend', function () {
    $juniorEditor = User::factory()->create(['role' => 'editor', 'editor_level' => 'junior']);
    $client = User::factory()->create(['role' => 'client', 'recommended_editor_level' => 'senior']);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => null,
        'service_id' => $this->basicService->id,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), [
            'editor_id' => $juniorEditor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('projects', [
        'id' => $project->id,
        'editor_id' => $juniorEditor->id,
    ]);
});
