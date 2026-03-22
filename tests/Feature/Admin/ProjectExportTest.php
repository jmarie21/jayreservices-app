<?php

use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('redirects guests away from the export endpoint', function () {
    $this->get(route('projects.all.export'))
        ->assertRedirect('/login');
});

it('forbids non-admin users from accessing the export', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)
        ->get(route('projects.all.export'))
        ->assertForbidden();
});

it('allows admins to download the projects excel export', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.all.export'))
        ->assertSuccessful()
        ->assertDownload();
});

it('exports with filename containing todays date', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.all.export'))
        ->assertDownload('projects-'.now()->format('Y-m-d').'.xlsx');
});

it('returns a successful response with no filters applied', function () {
    Project::factory()->count(3)->create();

    $this->actingAs($this->admin)
        ->get(route('projects.all.export'))
        ->assertSuccessful()
        ->assertDownload();
});

it('returns a successful response when status filter is applied', function () {
    Project::factory()->create(['status' => 'todo']);
    Project::factory()->create(['status' => 'in_progress']);

    $this->actingAs($this->admin)
        ->get(route('projects.all.export', ['status' => 'todo']))
        ->assertSuccessful()
        ->assertDownload();
});

it('returns a successful response when date range filter is applied', function () {
    Project::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('projects.all.export', [
            'date_from' => now()->subDays(7)->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]))
        ->assertSuccessful()
        ->assertDownload();
});

// Preview export tests

it('returns json data for export preview', function () {
    $project = Project::factory()->create(['status' => 'todo']);

    $this->actingAs($this->admin)
        ->getJson(route('projects.all.preview-export'))
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonFragment(['project_name' => $project->project_name]);
});

it('filters preview data by status', function () {
    Project::factory()->create(['status' => 'todo']);
    Project::factory()->create(['status' => 'in_progress']);

    $this->actingAs($this->admin)
        ->getJson(route('projects.all.preview-export', ['status' => 'todo']))
        ->assertSuccessful()
        ->assertJsonCount(1);
});

it('forbids non-admin users from accessing the preview', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)
        ->getJson(route('projects.all.preview-export'))
        ->assertForbidden();
});
