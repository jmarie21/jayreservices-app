<?php

use App\Models\ClientExtraRequest;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = User::factory()->create(['role' => 'client']);
    $this->editor = User::factory()->create(['role' => 'editor']);
    $this->service = Service::factory()->create();

    ClientExtraRequest::create([
        'client_id' => $this->client->id,
        'title' => 'Colorgrading style',
        'link' => 'https://example.com/reference',
        'description' => 'Warm, filmic tones',
    ]);

    $this->project = Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->service->id,
    ]);
});

it('exposes the client extra requests on the all projects page', function () {
    $this->actingAs($this->admin)
        ->get(route('projects.all'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/AllProjects')
            ->has('projects.data.0.client.extra_requests', 1)
            ->where('projects.data.0.client.extra_requests.0.title', 'Colorgrading style')
        );
});

it('exposes the client extra requests on the client projects page', function () {
    $this->actingAs($this->admin)
        ->get(route('client.projects', $this->client))
        ->assertInertia(fn ($page) => $page
            ->component('admin/ClientProjects')
            ->has('client.extra_requests', 1)
            ->where('client.extra_requests.0.title', 'Colorgrading style')
        );
});

it('exposes the client extra requests on the editor projects page', function () {
    $this->actingAs($this->editor)
        ->get(route('editor.projects.index'))
        ->assertInertia(fn ($page) => $page
            ->component('editor/EditorProjects')
            ->has('projects.data.0.client.extra_requests', 1)
            ->where('projects.data.0.client.extra_requests.0.title', 'Colorgrading style')
        );
});

it('exposes the client extra requests on the admin editor management page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.editor.projects', $this->editor))
        ->assertInertia(fn ($page) => $page
            ->component('admin/EditorManagement')
            ->has('projects.data.0.client.extra_requests', 1)
            ->where('projects.data.0.client.extra_requests.0.title', 'Colorgrading style')
        );
});

it('exposes the client own extra requests on their own projects page', function () {
    $this->actingAs($this->client)
        ->get(route('projects'))
        ->assertInertia(fn ($page) => $page
            ->component('client/Projects')
            ->has('projects.data.0.client.extra_requests', 1)
            ->where('projects.data.0.client.extra_requests.0.title', 'Colorgrading style')
        );
});

it('exposes the client own extra requests on the deep-linked project view', function () {
    $this->actingAs($this->client)
        ->get(route('projects', ['view' => $this->project->id]))
        ->assertInertia(fn ($page) => $page
            ->component('client/Projects')
            ->has('viewProject.client.extra_requests', 1)
            ->where('viewProject.client.extra_requests.0.title', 'Colorgrading style')
        );
});
