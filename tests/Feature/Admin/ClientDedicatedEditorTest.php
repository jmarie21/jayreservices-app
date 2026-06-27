<?php

use App\Models\ClientDedicatedEditor;
use App\Models\Service;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->basicService = Service::factory()->create(['name' => 'Real Estate Basic Style']);
});

it('redirects guests away from the dedicated editors page', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->get(route('admin.client-levels.dedicated-editors.edit', $client))
        ->assertRedirect('/login');
});

it('forbids non-admin users from accessing the dedicated editors page', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($editor)
        ->get(route('admin.client-levels.dedicated-editors.edit', $client))
        ->assertForbidden();
});

it('shows the current general and service-specific dedications for a client', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $serviceEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);
    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => $this->basicService->id, 'editor_id' => $serviceEditor->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.client-levels.dedicated-editors.edit', $client))
        ->assertInertia(fn ($page) => $page
            ->component('admin/ClientDedicatedEditors')
            ->where('client.id', $client->id)
            ->where('generalEditorIds', [$generalEditor->id])
            ->where('serviceDedications.0.service_id', $this->basicService->id)
            ->where('serviceDedications.0.editor_ids', [$serviceEditor->id])
        );
});

it('sets the general dedicated editors for a client', function () {
    $editorOne = User::factory()->create(['role' => 'editor']);
    $editorTwo = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editors.update', $client), [
            'service_id' => null,
            'editor_ids' => [$editorOne->id, $editorTwo->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('client_dedicated_editors', ['client_id' => $client->id, 'service_id' => null, 'editor_id' => $editorOne->id]);
    $this->assertDatabaseHas('client_dedicated_editors', ['client_id' => $client->id, 'service_id' => null, 'editor_id' => $editorTwo->id]);
});

it('sets service-specific dedicated editors without touching the general set', function () {
    $generalEditor = User::factory()->create(['role' => 'editor']);
    $serviceEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editors.update', $client), [
            'service_id' => $this->basicService->id,
            'editor_ids' => [$serviceEditor->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('client_dedicated_editors', ['client_id' => $client->id, 'service_id' => null, 'editor_id' => $generalEditor->id]);
    $this->assertDatabaseHas('client_dedicated_editors', ['client_id' => $client->id, 'service_id' => $this->basicService->id, 'editor_id' => $serviceEditor->id]);
});

it('replaces the previous editor set for the same bucket', function () {
    $oldEditor = User::factory()->create(['role' => 'editor']);
    $newEditor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $oldEditor->id]);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editors.update', $client), [
            'service_id' => null,
            'editor_ids' => [$newEditor->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('client_dedicated_editors', ['client_id' => $client->id, 'service_id' => null, 'editor_id' => $oldEditor->id]);
    $this->assertDatabaseHas('client_dedicated_editors', ['client_id' => $client->id, 'service_id' => null, 'editor_id' => $newEditor->id]);
});

it('clears a bucket when an empty editor list is submitted', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    ClientDedicatedEditor::create(['client_id' => $client->id, 'service_id' => null, 'editor_id' => $editor->id]);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editors.update', $client), [
            'service_id' => null,
            'editor_ids' => [],
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('client_dedicated_editors', ['client_id' => $client->id, 'service_id' => null, 'editor_id' => $editor->id]);
});

it('rejects an editor id that does not belong to an editor', function () {
    $otherClient = User::factory()->create(['role' => 'client']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editors.update', $client), [
            'service_id' => null,
            'editor_ids' => [$otherClient->id],
        ])
        ->assertSessionHasErrors(['editor_ids.0']);
});

it('rejects a service id that does not exist', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editors.update', $client), [
            'service_id' => 999999,
            'editor_ids' => [$editor->id],
        ])
        ->assertSessionHasErrors(['service_id']);
});

it('forbids non-admin users from updating dedicated editors', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($editor)
        ->patch(route('admin.client-levels.dedicated-editors.update', $client), [
            'service_id' => null,
            'editor_ids' => [$editor->id],
        ])
        ->assertForbidden();
});
