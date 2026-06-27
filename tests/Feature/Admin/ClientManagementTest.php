<?php

use App\Models\ClientDedicatedEditor;
use App\Models\ClientExtraRequest;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('redirects guests away from the client management index', function () {
    $this->get(route('admin.client-management.index'))
        ->assertRedirect('/login');
});

it('forbids non-admin users from accessing the client management index', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor)
        ->get(route('admin.client-management.index'))
        ->assertForbidden();
});

it('lists clients with their dedicated editor and extra request counts', function () {
    $client = User::factory()->create(['role' => 'client', 'recommended_editor_level' => 'mid']);
    $editor = User::factory()->create(['role' => 'editor']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $editor->id]);
    ClientExtraRequest::create(['client_id' => $client->id, 'title' => 'Colorgrading style', 'link' => null, 'description' => null]);

    $this->actingAs($this->admin)
        ->get(route('admin.client-management.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/ClientManagement')
            ->where('clients.0.id', $client->id)
            ->where('clients.0.recommended_editor_level', 'mid')
            ->where('clients.0.dedicated_editor_rules_count', 1)
            ->where('clients.0.extra_requests_count', 1)
        );
});

it('shows a client hub page with their extra requests', function () {
    $client = User::factory()->create(['role' => 'client']);

    ClientExtraRequest::create(['client_id' => $client->id, 'title' => 'Colorgrading style', 'link' => 'https://example.com', 'description' => 'Warm tones']);

    $this->actingAs($this->admin)
        ->get(route('admin.client-management.show', $client))
        ->assertInertia(fn ($page) => $page
            ->component('admin/ClientManagementShow')
            ->where('client.id', $client->id)
            ->where('client.extra_requests.0.title', 'Colorgrading style')
            ->where('client.extra_requests.0.link', 'https://example.com')
            ->where('client.extra_requests.0.description', 'Warm tones')
        );
});

it('forbids non-admin users from accessing a client hub page', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($editor)
        ->get(route('admin.client-management.show', $client))
        ->assertForbidden();
});
