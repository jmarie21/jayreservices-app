<?php

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('deletes notifications older than 10 days', function () {
    DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['message' => 'old'],
        'created_at' => now()->subDays(11),
    ]);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(0);
});

it('keeps notifications newer than 10 days', function () {
    DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['message' => 'recent'],
        'created_at' => now()->subDays(5),
    ]);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(1);
});

it('only deletes old notifications and keeps recent ones', function () {
    DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['message' => 'old'],
        'created_at' => now()->subDays(15),
    ]);

    DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['message' => 'recent'],
        'created_at' => now()->subDays(3),
    ]);

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(1);
    expect(DatabaseNotification::first()->data)->toBe(['message' => 'recent']);
});

it('accepts a custom days option', function () {
    DatabaseNotification::create([
        'id' => Str::uuid(),
        'type' => 'App\\Notifications\\TestNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $this->user->id,
        'data' => ['message' => 'five days old'],
        'created_at' => now()->subDays(5),
    ]);

    $this->artisan('notifications:prune --days=3')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(0);
});
