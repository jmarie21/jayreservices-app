<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

        $projectsCount = Project::count();
        $clientsCount = User::where('role', 'client')->count();
        $activeEditors = User::where('role', 'editor')->count();

        $weeklyRevenue = (float) Project::whereBetween('created_at', [$weekStart, $weekEnd])->sum('total_price');
        $lastWeekRevenue = (float) Project::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->sum('total_price');
        $revenueChangePercent = $lastWeekRevenue > 0
            ? round((($weeklyRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 1)
            : ($weeklyRevenue > 0 ? 100.0 : null);

        $thisWeekProjectsCount = Project::whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $lastWeekProjectsCount = Project::whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $weeklyProjectsDelta = $thisWeekProjectsCount - $lastWeekProjectsCount;

        $thisWeekClientsCount = User::where('role', 'client')->whereBetween('created_at', [$weekStart, $weekEnd])->count();
        $lastWeekClientsCount = User::where('role', 'client')->whereBetween('created_at', [$lastWeekStart, $lastWeekEnd])->count();
        $weeklyClientsDelta = $thisWeekClientsCount - $lastWeekClientsCount;

        // Pipeline status breakdown for multiple periods (all client-side filtered)
        $pipelinePeriods = [
            'all' => null,
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            'month' => Carbon::now()->startOfMonth(),
        ];

        $projectsByStatusByPeriod = [];
        foreach ($pipelinePeriods as $key => $since) {
            $query = Project::query();
            if ($since) {
                $query->where('created_at', '>=', $since);
            }
            $projectsByStatusByPeriod[$key] = $query->selectRaw('status, count(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status');
        }

        $editorWorkload = User::where('role', 'editor')
            ->withCount([
                'assignedProjects as active_count' => fn ($q) => $q->whereIn('status', ['todo', 'in_progress', 'backlog', 'for_qa', 'done_qa']),
                'assignedProjects as revision_count' => fn ($q) => $q->whereIn('status', ['revision', 'revision_completed']),
                'assignedProjects as total_count',
            ])
            ->get()
            ->map(fn ($editor) => [
                'name' => $editor->name,
                'active' => $editor->active_count,
                'revision' => $editor->revision_count,
                'total' => $editor->total_count,
            ]);

        $serviceBreakdown = Service::withCount('projects')
            ->withSum('projects', 'total_price')
            ->orderByDesc('projects_count')
            ->get()
            ->map(fn ($service) => [
                'name' => $service->name,
                'count' => $service->projects_count,
                'revenue' => (float) ($service->projects_sum_total_price ?? 0),
            ]);

        $topClients = User::where('role', 'client')
            ->withCount('projects')
            ->withSum('projects', 'total_price')
            ->orderByDesc('projects_count')
            ->take(5)
            ->get()
            ->map(fn ($client) => [
                'name' => $client->name,
                'count' => $client->projects_count,
                'revenue' => (float) ($client->projects_sum_total_price ?? 0),
            ]);

        // Revenue trend with date range filter
        $trendFrom = $request->query('trend_from', Carbon::now()->subDays(6)->format('Y-m-d'));
        $trendTo = $request->query('trend_to', Carbon::now()->format('Y-m-d'));

        $from = Carbon::parse($trendFrom)->startOfDay();
        $to = Carbon::parse($trendTo)->endOfDay();

        if ($from->gt($to)) {
            $from = $to->copy()->subDays(6);
        }

        if ($from->diffInDays($to) > 365) {
            $from = $to->copy()->subDays(364);
        }

        $daysDiff = (int) $from->diffInDays($to);
        $revenueTrend = collect(range(0, $daysDiff))->map(function ($offset) use ($from) {
            $date = $from->copy()->addDays($offset);
            $query = Project::whereDate('created_at', $date);

            return [
                'day' => $date->format('M j'),
                'revenue' => (float) $query->sum('total_price'),
                'count' => $query->count(),
            ];
        })->values();

        $recentProjects = Project::with(['client', 'service'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($project) => [
                'id' => $project->id,
                'project_name' => $project->project_name,
                'client_name' => $project->client?->name ?? 'Unknown',
                'status' => $project->status,
                'service_name' => $project->service?->name ?? '—',
                'priority' => $project->priority,
                'rush' => $project->rush,
            ]);

        return Inertia::render('admin/AdminDashboard', [
            'dashboard' => [
                'projectsCount' => $projectsCount,
                'clientsCount' => $clientsCount,
                'activeEditors' => $activeEditors,
                'weeklyRevenue' => $weeklyRevenue,
                'weeklyProjectsDelta' => $weeklyProjectsDelta,
                'weeklyClientsDelta' => $weeklyClientsDelta,
                'revenueChangePercent' => $revenueChangePercent,
                'projectsByStatusByPeriod' => $projectsByStatusByPeriod,
                'editorWorkload' => $editorWorkload,
                'serviceBreakdown' => $serviceBreakdown,
                'topClients' => $topClients,
                'trendFrom' => $from->format('Y-m-d'),
                'trendTo' => $to->format('Y-m-d'),
                'revenueTrend' => $revenueTrend,
                'recentProjects' => $recentProjects,
            ],
        ]);
    }
}
