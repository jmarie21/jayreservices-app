<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $currentMonthStart = Carbon::now()->startOfMonth();
        $currentMonthEnd = Carbon::now()->endOfMonth();

        // Total Projects
        $projectsCount = Project::count();

        // Total Clients (users with role 'client')
        $clientsCount = User::where('role', 'client')->count();

        // Active editors (users with role 'editor' and logged in recently)
        $activeEditors = User::where('role', 'editor')->count();

        // Monthly Profit (sum of paid invoices in current month)
        $monthlyProfit = Invoice::where('status', 'paid')
            ->whereBetween('created_at', [$currentMonthStart, $currentMonthEnd])
            ->sum('total_amount');

        // Recent projects with client names
        $recentProjects = Project::with('client')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($project) => [
                'id' => $project->id,
                'project_name' => $project->project_name,
                'client_name' => $project->client?->name ?? 'Unknown',
            ]);

        return Inertia::render('admin/AdminDashboard', [
            'dashboard' => [
                'projectsCount' => $projectsCount,
                'clientsCount' => $clientsCount,
                'activeEditors' => $activeEditors,
                'monthlyProfit' => $monthlyProfit,
                'recentProjects' => $recentProjects,
            ],
        ]);
    }
}
