<?php

use App\Models\ClientExtraRequest;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = User::factory()->create(['role' => 'client']);
});

it('creates an extra request for a client', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.client-management.extra-requests.store', $this->client), [
            'title' => 'Colorgrading style',
            'link' => 'https://example.com/reference',
            'description' => 'Warm, filmic tones',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('client_extra_requests', [
        'client_id' => $this->client->id,
        'title' => 'Colorgrading style',
        'link' => 'https://example.com/reference',
        'description' => 'Warm, filmic tones',
    ]);
});

it('prefixes a bare link with https when creating a request', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.client-management.extra-requests.store', $this->client), [
            'title' => 'Reference video',
            'link' => 'example.com/reference',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('client_extra_requests', [
        'client_id' => $this->client->id,
        'link' => 'https://example.com/reference',
    ]);
});

it('requires a title when creating a request', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.client-management.extra-requests.store', $this->client), [
            'title' => '',
        ])
        ->assertSessionHasErrors(['title']);
});

it('updates an existing extra request', function () {
    $request = ClientExtraRequest::create([
        'client_id' => $this->client->id,
        'title' => 'Old title',
        'link' => null,
        'description' => null,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.client-management.extra-requests.update', [$this->client, $request]), [
            'title' => 'New title',
            'link' => null,
            'description' => 'Updated description',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('client_extra_requests', [
        'id' => $request->id,
        'title' => 'New title',
        'description' => 'Updated description',
    ]);
});

it('deletes an extra request', function () {
    $request = ClientExtraRequest::create([
        'client_id' => $this->client->id,
        'title' => 'Colorgrading style',
        'link' => null,
        'description' => null,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('admin.client-management.extra-requests.destroy', [$this->client, $request]))
        ->assertRedirect();

    $this->assertDatabaseMissing('client_extra_requests', ['id' => $request->id]);
});

it('forbids non-admin users from managing extra requests', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor)
        ->post(route('admin.client-management.extra-requests.store', $this->client), [
            'title' => 'Colorgrading style',
        ])
        ->assertForbidden();
});
