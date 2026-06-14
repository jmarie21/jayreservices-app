<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->editor = User::factory()->create(['role' => 'editor']);
    $this->client = User::factory()->create(['role' => 'client']);
    $this->basicService = Service::factory()->create(['name' => 'Real Estate Basic Style']);
});

it('returns only overdue projects when filtering by the overdue status', function () {
    $overdue = Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'project_name' => 'Overdue Project',
        'status' => 'in_progress',
        'in_progress_since' => now()->subHours(13), // past the 12h basic deadline
    ]);

    // Fresh in_progress project, still within its deadline
    Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'project_name' => 'Fresh Project',
        'status' => 'in_progress',
        'in_progress_since' => now()->subHour(),
    ]);

    // Non-timer status: even though it is old, it can never be overdue
    Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'project_name' => 'For QA Project',
        'status' => 'for_qa',
        'in_progress_since' => now()->subHours(50),
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.all', ['status' => 'overdue']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/AllProjects')
            ->where('filters.status', 'overdue')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $overdue->id)
            ->where('projects.data.0.project_name', 'Overdue Project')
        );
});

it('includes revision projects past the revision deadline as overdue', function () {
    $overdueRevision = Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'project_name' => 'Overdue Revision',
        'status' => 'revision',
        'revision_since' => now()->subHours(4), // past the 3h revision deadline
    ]);

    // Fresh revision, still within the 3h deadline
    Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'project_name' => 'Fresh Revision',
        'status' => 'revision',
        'revision_since' => now()->subHour(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.all', ['status' => 'overdue']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/AllProjects')
            ->has('projects.data', 1)
            ->where('projects.data.0.id', $overdueRevision->id)
        );
});

it('respects the extended package deadline when determining overdue projects', function () {
    $luxuryService = Service::factory()->create(['name' => 'Real Estate Luxury Style']);

    // Luxury + package = 46h deadline; 40h in is NOT overdue yet
    Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $luxuryService->id,
        'project_name' => 'Luxury Package Not Overdue',
        'status' => 'in_progress',
        'rush' => false,
        'format' => Project::PACKAGE_FORMAT,
        'in_progress_since' => now()->subHours(40),
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.all', ['status' => 'overdue']))
        ->assertInertia(fn ($page) => $page
            ->component('admin/AllProjects')
            ->has('projects.data', 0)
        );
});

it('returns every project when no status filter is applied', function () {
    Project::factory()->count(3)->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHour(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('projects.all'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/AllProjects')
            ->has('projects.data', 3)
        );
});
