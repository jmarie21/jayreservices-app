<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('redirects guests away from the client levels page', function () {
    $this->get(route('admin.client-levels.index'))
        ->assertRedirect('/login');
});

it('forbids non-admin users from accessing the client levels page', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor)
        ->get(route('admin.client-levels.index'))
        ->assertForbidden();
});

it('lists only clients with their recommended level on the index page', function () {
    $client = User::factory()->create(['role' => 'client', 'recommended_editor_level' => 'mid']);
    User::factory()->create(['role' => 'editor']);

    $this->actingAs($this->admin)
        ->get(route('admin.client-levels.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/ClientLevels')
            ->has('clients', 1)
            ->where('clients.0.id', $client->id)
            ->where('clients.0.recommended_editor_level', 'mid')
        );
});

it('includes the editors list and each client dedicated editor on the index page', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client', 'dedicated_editor_id' => $editor->id]);

    $this->actingAs($this->admin)
        ->get(route('admin.client-levels.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/ClientLevels')
            ->has('editors', 1)
            ->where('editors.0.id', $editor->id)
            ->where('clients.0.dedicated_editor_id', $editor->id)
        );
});

it('bulk assigns a recommended level to multiple clients', function () {
    $clientOne = User::factory()->create(['role' => 'client']);
    $clientTwo = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.assign'), [
            'level' => 'junior',
            'user_ids' => [$clientOne->id, $clientTwo->id],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $clientOne->id, 'recommended_editor_level' => 'junior']);
    $this->assertDatabaseHas('users', ['id' => $clientTwo->id, 'recommended_editor_level' => 'junior']);
});

it('moves a client back to unassigned when level is null', function () {
    $client = User::factory()->create(['role' => 'client', 'recommended_editor_level' => 'senior']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.assign'), [
            'level' => null,
            'user_ids' => [$client->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $client->id, 'recommended_editor_level' => null]);
});

it('rejects an invalid level value', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.assign'), [
            'level' => 'lead',
            'user_ids' => [$client->id],
        ])
        ->assertSessionHasErrors(['level']);
});

it('rejects user ids that do not belong to a client', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.assign'), [
            'level' => 'senior',
            'user_ids' => [$editor->id],
        ])
        ->assertSessionHasErrors(['user_ids.0']);

    $this->assertDatabaseHas('users', ['id' => $editor->id, 'recommended_editor_level' => null]);
});

it('sets a dedicated editor for a client', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editor', $client), [
            'editor_id' => $editor->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $client->id, 'dedicated_editor_id' => $editor->id]);
});

it('clears a client dedicated editor when editor_id is null', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client', 'dedicated_editor_id' => $editor->id]);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editor', $client), [
            'editor_id' => null,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $client->id, 'dedicated_editor_id' => null]);
});

it('rejects a dedicated editor id that does not belong to an editor', function () {
    $otherClient = User::factory()->create(['role' => 'client']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->patch(route('admin.client-levels.dedicated-editor', $client), [
            'editor_id' => $otherClient->id,
        ])
        ->assertSessionHasErrors(['editor_id']);

    $this->assertDatabaseHas('users', ['id' => $client->id, 'dedicated_editor_id' => null]);
});

it('forbids non-admin users from setting a dedicated editor', function () {
    $editor = User::factory()->create(['role' => 'editor']);
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($editor)
        ->patch(route('admin.client-levels.dedicated-editor', $client), [
            'editor_id' => $editor->id,
        ])
        ->assertForbidden();
});
