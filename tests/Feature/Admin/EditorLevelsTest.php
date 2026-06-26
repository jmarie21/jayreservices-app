<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

it('redirects guests away from the editor levels page', function () {
    $this->get(route('admin.editor-levels.index'))
        ->assertRedirect('/login');
});

it('forbids non-admin users from accessing the editor levels page', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($client)
        ->get(route('admin.editor-levels.index'))
        ->assertForbidden();
});

it('lists only editors with their level on the index page', function () {
    $editor = User::factory()->create(['role' => 'editor', 'editor_level' => 'senior']);
    User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->get(route('admin.editor-levels.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/EditorLevels')
            ->has('editors', 1)
            ->where('editors.0.id', $editor->id)
            ->where('editors.0.editor_level', 'senior')
        );
});

it('bulk assigns a level to multiple editors', function () {
    $editorOne = User::factory()->create(['role' => 'editor']);
    $editorTwo = User::factory()->create(['role' => 'editor']);

    $response = $this->actingAs($this->admin)
        ->patch(route('admin.editor-levels.assign'), [
            'level' => 'senior',
            'user_ids' => [$editorOne->id, $editorTwo->id],
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $editorOne->id, 'editor_level' => 'senior']);
    $this->assertDatabaseHas('users', ['id' => $editorTwo->id, 'editor_level' => 'senior']);
});

it('moves an editor back to unassigned when level is null', function () {
    $editor = User::factory()->create(['role' => 'editor', 'editor_level' => 'senior']);

    $this->actingAs($this->admin)
        ->patch(route('admin.editor-levels.assign'), [
            'level' => null,
            'user_ids' => [$editor->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['id' => $editor->id, 'editor_level' => null]);
});

it('rejects an invalid level value', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($this->admin)
        ->patch(route('admin.editor-levels.assign'), [
            'level' => 'lead',
            'user_ids' => [$editor->id],
        ])
        ->assertSessionHasErrors(['level']);
});

it('rejects user ids that do not belong to an editor', function () {
    $client = User::factory()->create(['role' => 'client']);

    $this->actingAs($this->admin)
        ->patch(route('admin.editor-levels.assign'), [
            'level' => 'senior',
            'user_ids' => [$client->id],
        ])
        ->assertSessionHasErrors(['user_ids.0']);

    $this->assertDatabaseHas('users', ['id' => $client->id, 'editor_level' => null]);
});
