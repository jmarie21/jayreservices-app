<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ProjectStalledNotification;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->editor = User::factory()->create(['role' => 'editor']);
    $this->basicService = Service::factory()->create(['name' => 'Real Estate Basic Style']);
    $this->premiumService = Service::factory()->create(['name' => 'Real Estate Premium Style']);
});

// --- CheckStalledProjects Command Tests ---

it('auto-unassigns a basic service project stalled for 12+ hours', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHours(13),
    ]);

    $this->artisan('projects:check-stalled')->assertSuccessful();

    $project->refresh();
    expect($project->status)->toBe('overdue');
    expect($project->editor_id)->toBe($this->editor->id);
    expect($project->in_progress_since)->not->toBeNull();
});

it('does not mark a premium service project as overdue at 12 hours', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->premiumService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHours(13),
    ]);

    $this->artisan('projects:check-stalled')->assertSuccessful();

    $project->refresh();
    expect($project->status)->toBe('in_progress');
    expect($project->editor_id)->toBe($this->editor->id);
});

it('marks a premium service project as overdue after 24+ hours', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->premiumService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHours(25),
    ]);

    $this->artisan('projects:check-stalled')->assertSuccessful();

    $project->refresh();
    expect($project->status)->toBe('overdue');
    expect($project->editor_id)->toBe($this->editor->id);
    expect($project->in_progress_since)->not->toBeNull();
});

it('does not touch a fresh in_progress project', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHour(),
    ]);

    $this->artisan('projects:check-stalled')->assertSuccessful();

    $project->refresh();
    expect($project->status)->toBe('in_progress');
    expect($project->editor_id)->toBe($this->editor->id);
});

it('does not touch non-in_progress statuses', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'for_qa',
        'in_progress_since' => now()->subHours(25),
    ]);

    $this->artisan('projects:check-stalled')->assertSuccessful();

    $project->refresh();
    expect($project->status)->toBe('for_qa');
});

it('sends admin notification on auto-unassign', function () {
    Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHours(13),
    ]);

    $this->artisan('projects:check-stalled')->assertSuccessful();

    Notification::assertSentTo($this->admin, ProjectStalledNotification::class);
});

// --- Forward-Only Enforcement Tests ---

it('allows editor to move status forward (in_progress to for_qa)', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'in_progress',
        'in_progress_since' => now(),
    ]);

    $this->actingAs($this->editor)
        ->patch(route('editor.projects.update', $project), ['status' => 'for_qa'])
        ->assertRedirect();

    $project->refresh();
    expect($project->status)->toBe('for_qa');
});

it('blocks editor from moving status backward (for_qa to in_progress)', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'for_qa',
    ]);

    $this->actingAs($this->editor)
        ->patch(route('editor.projects.update', $project), ['status' => 'in_progress'])
        ->assertStatus(422);
});

it('blocks editor from setting cancelled status', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'todo',
    ]);

    $this->actingAs($this->editor)
        ->patch(route('editor.projects.update', $project), ['status' => 'cancelled'])
        ->assertStatus(422);
});

it('blocks editor from moving from sent_to_client', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'sent_to_client',
    ]);

    $this->actingAs($this->editor)
        ->patch(route('editor.projects.update', $project), ['status' => 'in_progress'])
        ->assertStatus(422);
});

// --- Timer Tracking Tests ---

it('sets in_progress_since when editor moves to in_progress', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'todo',
        'in_progress_since' => null,
    ]);

    $this->actingAs($this->editor)
        ->patch(route('editor.projects.update', $project), ['status' => 'in_progress'])
        ->assertRedirect();

    $project->refresh();
    expect($project->in_progress_since)->not->toBeNull();
});

it('clears in_progress_since when editor moves forward from in_progress', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHours(5),
    ]);

    $this->actingAs($this->editor)
        ->patch(route('editor.projects.update', $project), ['status' => 'for_qa'])
        ->assertRedirect();

    $project->refresh();
    expect($project->in_progress_since)->toBeNull();
});

// --- Admin Re-assign Tests ---

it('clears timer when admin re-assigns to a different editor', function () {
    $newEditor = User::factory()->create(['role' => 'editor']);

    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'in_progress',
        'in_progress_since' => now()->subHours(5),
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), ['editor_id' => $newEditor->id])
        ->assertRedirect();

    $project->refresh();
    expect($project->in_progress_since)->toBeNull();
    expect($project->status)->toBe('todo');
    expect($project->editor_id)->toBe($newEditor->id);
});

it('allows admin to set any status without forward-only restriction', function () {
    $project = Project::factory()->create([
        'editor_id' => $this->editor->id,
        'service_id' => $this->basicService->id,
        'status' => 'for_qa',
    ]);

    $this->actingAs($this->admin)
        ->patch(route('projects.admin_update', $project), ['status' => 'in_progress'])
        ->assertRedirect();

    $project->refresh();
    expect($project->status)->toBe('in_progress');
});
