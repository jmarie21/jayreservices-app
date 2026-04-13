<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('provides the deep-linked project for the client modal even when it is outside the current page of results', function () {
    $client = User::factory()->create(['role' => 'client']);
    $service = Service::factory()->create();

    $targetProject = Project::factory()->create([
        'client_id' => $client->id,
        'service_id' => $service->id,
        'project_name' => 'Deep Linked Project',
        'created_at' => now()->subDays(20),
    ]);

    Project::factory()->count(10)->create([
        'client_id' => $client->id,
        'service_id' => $service->id,
    ]);

    $this->actingAs($client)
        ->get(route('projects', ['view' => $targetProject->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('client/Projects')
            ->where('viewProjectId', $targetProject->id)
            ->where('viewProject.id', $targetProject->id)
            ->where('viewProject.project_name', 'Deep Linked Project')
            ->has('projects.data', 10)
        );
});
