<?php

use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->client = User::factory()->create(['role' => 'client']);
    $this->editor = User::factory()->create(['role' => 'editor']);
    $this->service = Service::factory()->create(['name' => 'Real Estate Basic']);
});

it('redirects guests to login', function () {
    $this->get(route('dashboard'))->assertRedirect('/login');
});

it('renders the admin dashboard for admin users', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->component('admin/AdminDashboard'));
});

it('returns correct project count', function () {
    Project::factory()->count(3)->create(['client_id' => $this->client->id, 'service_id' => $this->service->id]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.projectsCount', 3)
        );
});

it('returns correct client and editor counts', function () {
    User::factory()->count(2)->create(['role' => 'client']);
    User::factory()->count(1)->create(['role' => 'editor']);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.clientsCount', 3) // 1 from beforeEach + 2 new
            ->where('dashboard.activeEditors', 2) // 1 from beforeEach + 1 new
        );
});

it('returns weekly revenue based on project total_price this week', function () {
    Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'total_price' => '500.00',
        'created_at' => Carbon::now()->startOfWeek()->addDay(),
    ]);

    Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'total_price' => '300.00',
        'created_at' => Carbon::now()->subWeeks(2),
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.weeklyRevenue', 500)
        );
});

it('returns weekly projects delta comparing this week to last week', function () {
    Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'created_at' => Carbon::now()->startOfWeek()->addDay(),
    ]);

    Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'created_at' => Carbon::now()->subWeek()->startOfWeek()->addDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.weeklyProjectsDelta', 0) // 1 this week - 1 last week
        );
});

it('returns weekly clients delta comparing this week to last week', function () {
    User::factory()->create([
        'role' => 'client',
        'created_at' => Carbon::now()->startOfWeek()->addDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.weeklyClientsDelta')
        );
});

it('returns revenue change percent when last week had revenue', function () {
    Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'total_price' => '200.00',
        'created_at' => Carbon::now()->subWeek()->startOfWeek()->addDay(),
    ]);

    Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'total_price' => '400.00',
        'created_at' => Carbon::now()->startOfWeek()->addDay(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.revenueChangePercent', 100) // 100% increase
        );
});

it('returns null revenue change percent when last week had no revenue', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('dashboard.revenueChangePercent', null)
        );
});

it('returns projects grouped by status for all periods', function () {
    Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'status' => 'in_progress',
        'created_at' => now(),
    ]);

    Project::factory()->count(2)->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'status' => 'todo',
        'created_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.projectsByStatusByPeriod')
            ->where('dashboard.projectsByStatusByPeriod.all.in_progress', 1)
            ->where('dashboard.projectsByStatusByPeriod.all.todo', 2)
            ->where('dashboard.projectsByStatusByPeriod.7d.in_progress', 1)
            ->where('dashboard.projectsByStatusByPeriod.7d.todo', 2)
            ->has('dashboard.projectsByStatusByPeriod.30d')
            ->has('dashboard.projectsByStatusByPeriod.month')
        );
});

it('returns editor workload with active and revision counts', function () {
    Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->service->id,
        'status' => 'in_progress',
    ]);

    Project::factory()->create([
        'client_id' => $this->client->id,
        'editor_id' => $this->editor->id,
        'service_id' => $this->service->id,
        'status' => 'revision',
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.editorWorkload', 1)
            ->where('dashboard.editorWorkload.0.name', $this->editor->name)
            ->where('dashboard.editorWorkload.0.active', 1)
            ->where('dashboard.editorWorkload.0.revision', 1)
            ->where('dashboard.editorWorkload.0.total', 2)
        );
});

it('returns service breakdown ordered by project count', function () {
    $other = Service::factory()->create(['name' => 'Premium Style']);

    Project::factory()->count(3)->create(['client_id' => $this->client->id, 'service_id' => $this->service->id, 'total_price' => '100.00']);
    Project::factory()->count(1)->create(['client_id' => $this->client->id, 'service_id' => $other->id, 'total_price' => '200.00']);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.serviceBreakdown')
            ->where('dashboard.serviceBreakdown.0.name', 'Real Estate Basic')
            ->where('dashboard.serviceBreakdown.0.count', 3)
            ->where('dashboard.serviceBreakdown.0.revenue', 300)
            ->where('dashboard.serviceBreakdown.1.name', 'Premium Style')
            ->where('dashboard.serviceBreakdown.1.count', 1)
        );
});

it('returns top clients ordered by project count', function () {
    $otherClient = User::factory()->create(['role' => 'client']);

    Project::factory()->count(2)->create(['client_id' => $this->client->id, 'service_id' => $this->service->id, 'total_price' => '150.00']);
    Project::factory()->count(1)->create(['client_id' => $otherClient->id, 'service_id' => $this->service->id, 'total_price' => '200.00']);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.topClients')
            ->where('dashboard.topClients.0.name', $this->client->name)
            ->where('dashboard.topClients.0.count', 2)
            ->where('dashboard.topClients.0.revenue', 300)
        );
});

it('returns at most 5 top clients', function () {
    User::factory()->count(6)->create(['role' => 'client'])->each(function ($client) {
        Project::factory()->create(['client_id' => $client->id, 'service_id' => $this->service->id]);
    });

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.topClients', 5)
        );
});

it('returns 7 days in the revenue trend by default', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.revenueTrend', 7)
            ->has('dashboard.revenueTrend.0', fn ($trend) => $trend
                ->has('day')
                ->has('revenue')
                ->has('count')
            )
            ->where('dashboard.trendFrom', Carbon::now()->subDays(6)->format('Y-m-d'))
            ->where('dashboard.trendTo', Carbon::now()->format('Y-m-d'))
        );
});

it('returns revenue trend for a custom date range', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard', ['trend_from' => '2024-03-01', 'trend_to' => '2024-03-10']))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.revenueTrend', 10)
            ->where('dashboard.trendFrom', '2024-03-01')
            ->where('dashboard.trendTo', '2024-03-10')
        );
});

it('caps the revenue trend range to 365 days', function () {
    $from = Carbon::now()->subDays(500)->format('Y-m-d');
    $to = Carbon::now()->format('Y-m-d');

    $this->actingAs($this->admin)
        ->get(route('dashboard', ['trend_from' => $from, 'trend_to' => $to]))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.revenueTrend', 365)
        );
});

it('returns recent projects with expanded fields', function () {
    $project = Project::factory()->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
        'status' => 'in_progress',
        'priority' => 'high',
        'rush' => true,
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.recentProjects', 1)
            ->where('dashboard.recentProjects.0.id', $project->id)
            ->where('dashboard.recentProjects.0.status', 'in_progress')
            ->where('dashboard.recentProjects.0.priority', 'high')
            ->where('dashboard.recentProjects.0.rush', true)
            ->has('dashboard.recentProjects.0.service_name')
        );
});

it('returns at most 8 recent projects', function () {
    Project::factory()->count(10)->create([
        'client_id' => $this->client->id,
        'service_id' => $this->service->id,
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('dashboard.recentProjects', 8)
        );
});
