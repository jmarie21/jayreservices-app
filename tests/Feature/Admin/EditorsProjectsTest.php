<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-04-11 10:15:00');

    $this->admin = User::factory()->create(['role' => 'admin']);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('redirects guests away from the editors projects page', function () {
    $this->get(route('admin.editors-projects.index'))
        ->assertRedirect('/login');
});

it('forbids non-admin users from accessing the editors projects page', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)
        ->get(route('admin.editors-projects.index'))
        ->assertForbidden();
});

it('defaults the date filter to today and keeps editors ordered with empty project groups visible', function () {
    $alphaEditor = User::factory()->create([
        'role' => 'editor',
        'name' => 'Alpha Editor',
        'email' => 'alpha@example.com',
    ]);
    $zedEditor = User::factory()->create([
        'role' => 'editor',
        'name' => 'Zed Editor',
        'email' => 'zed@example.com',
    ]);
    $client = User::factory()->create([
        'role' => 'client',
        'name' => 'Client One',
    ]);
    $service = Service::factory()->create([
        'name' => 'Luxury Listing',
    ]);

    Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $alphaEditor->id,
        'service_id' => $service->id,
        'project_name' => 'Today Project',
        'format' => null,
        'rush' => true,
        'priority' => 'urgent',
        'total_price' => 2500.75,
        'editor_price' => 900.25,
        'created_at' => Carbon::today()->setTime(9, 30),
    ]);

    Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $zedEditor->id,
        'service_id' => $service->id,
        'project_name' => 'Yesterday Project',
        'format' => 'Vertical',
        'priority' => 'normal',
        'total_price' => 1800,
        'editor_price' => 700,
        'created_at' => Carbon::yesterday()->setTime(15, 0),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.editors-projects.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/EditorsProjects')
            ->where('filters.date_from', Carbon::today()->toDateString())
            ->where('filters.date_to', Carbon::today()->toDateString())
            ->has('editors', 2)
            ->where('editors.0.name', 'Alpha Editor')
            ->has('editors.0.projects', 1)
            ->where('editors.0.projects.0.client_name', 'Client One')
            ->where('editors.0.projects.0.project_name', 'Today Project')
            ->where('editors.0.projects.0.service', 'Luxury Listing')
            ->where('editors.0.projects.0.video_format', 'N/A')
            ->where('editors.0.projects.0.add_ons', 'Rush')
            ->where('editors.0.projects.0.priority', 'urgent')
            ->where('editors.0.projects.0.total_price', 2500.75)
            ->where('editors.0.projects.0.editor_price', 900.25)
            ->where('editors.1.name', 'Zed Editor')
            ->has('editors.1.projects', 0)
        );
});

it('applies a custom inclusive date range to each editor group', function () {
    $alphaEditor = User::factory()->create([
        'role' => 'editor',
        'name' => 'Alpha Editor',
    ]);
    $betaEditor = User::factory()->create([
        'role' => 'editor',
        'name' => 'Beta Editor',
    ]);
    $client = User::factory()->create(['role' => 'client']);
    $service = Service::factory()->create([
        'name' => 'Premium Tour',
    ]);

    Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $alphaEditor->id,
        'service_id' => $service->id,
        'project_name' => 'Included Project',
        'created_at' => Carbon::parse('2026-04-09 11:00:00'),
    ]);

    Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $alphaEditor->id,
        'service_id' => $service->id,
        'project_name' => 'Excluded Project',
        'created_at' => Carbon::parse('2026-04-11 11:00:00'),
    ]);

    Project::factory()->create([
        'client_id' => $client->id,
        'editor_id' => $betaEditor->id,
        'service_id' => $service->id,
        'project_name' => 'Beta Included Project',
        'created_at' => Carbon::parse('2026-04-09 14:00:00'),
    ]);

    $this->actingAs($this->admin)
        ->get(route('admin.editors-projects.index', [
            'date_from' => '2026-04-09',
            'date_to' => '2026-04-09',
        ]))
        ->assertInertia(fn ($page) => $page
            ->component('admin/EditorsProjects')
            ->where('filters.date_from', '2026-04-09')
            ->where('filters.date_to', '2026-04-09')
            ->has('editors', 2)
            ->has('editors.0.projects', 1)
            ->where('editors.0.projects.0.project_name', 'Included Project')
            ->has('editors.1.projects', 1)
            ->where('editors.1.projects.0.project_name', 'Beta Included Project')
        );
});
