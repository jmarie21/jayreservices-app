<?php

use App\Events\UserDeactivated;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);
});

it('includes user active status on the user management page', function () {
    $inactiveUser = User::factory()->create([
        'role' => 'client',
        'is_active' => false,
    ]);

    $this->actingAs($this->admin)
        ->get(route('user-mgmt'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/UserManagement')
            ->has('users', 2)
            ->where('users.1.id', $inactiveUser->id)
            ->where('users.1.is_active', false)
        );
});

it('allows admins to create inactive users', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('user-mgmt.store'), [
            'name' => 'Inactive Client',
            'email' => 'inactive-client@example.com',
            'password' => 'password',
            'role' => 'client',
            'is_active' => false,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email' => 'inactive-client@example.com',
        'role' => 'client',
        'is_active' => false,
    ]);
});

it('deactivates another user, invalidates their sessions, rotates remember token, and dispatches the logout event', function () {
    Event::fake([UserDeactivated::class]);

    $targetUser = User::factory()->create([
        'role' => 'client',
        'is_active' => true,
        'remember_token' => 'known-token',
    ]);

    DB::table('sessions')->insert([
        'id' => 'target-session',
        'user_id' => $targetUser->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'serialized-session',
        'last_activity' => now()->timestamp,
    ]);

    $response = $this->actingAs($this->admin)
        ->put(route('user-mgmt.update', $targetUser), [
            'name' => $targetUser->name,
            'email' => $targetUser->email,
            'role' => $targetUser->role,
            'is_active' => false,
            'additional_emails' => null,
        ]);

    $response->assertRedirect();

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'is_active' => false,
    ]);
    $this->assertDatabaseMissing('sessions', [
        'id' => 'target-session',
    ]);

    $targetUser->refresh();

    expect($targetUser->remember_token)->not->toBe('known-token');

    Event::assertDispatched(UserDeactivated::class, function (UserDeactivated $event) use ($targetUser) {
        return $event->user->id === $targetUser->id;
    });
});

it('does not allow admins to deactivate their own account', function () {
    User::factory()->create([
        'role' => 'admin',
        'is_active' => true,
    ]);

    $response = $this->from(route('user-mgmt'))
        ->actingAs($this->admin)
        ->put(route('user-mgmt.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'admin',
            'is_active' => false,
            'additional_emails' => null,
        ]);

    $response->assertRedirect(route('user-mgmt'));
    $response->assertSessionHasErrors([
        'is_active' => 'You cannot deactivate your own account.',
    ]);

    $this->admin->refresh();

    expect($this->admin->is_active)->toBeTrue();
});

it('does not allow the last active admin to be changed to a non-admin role', function () {
    $response = $this->from(route('user-mgmt'))
        ->actingAs($this->admin)
        ->put(route('user-mgmt.update', $this->admin), [
            'name' => $this->admin->name,
            'email' => $this->admin->email,
            'role' => 'client',
            'is_active' => true,
            'additional_emails' => null,
        ]);

    $response->assertRedirect(route('user-mgmt'));
    $response->assertSessionHasErrors([
        'role' => 'You must keep at least one active admin account.',
    ]);

    $this->admin->refresh();

    expect($this->admin->role)->toBe('admin');
});
