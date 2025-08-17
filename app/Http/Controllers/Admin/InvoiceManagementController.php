<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvoiceManagementController extends Controller
{
    public function index(Request $request)
    {
        // Get all clients
        $clients = User::where('role', 'client')->get(['id', 'name']);

        if ($request->filled('client_id')) {
            $projectsQuery = Project::with(['service', 'client'])
                ->where('client_id', $request->client_id);

            if ($request->filled('date_from')) {
                $projectsQuery->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $projectsQuery->whereDate('created_at', '<=', $request->date_to);
            }

            $projects = $projectsQuery->orderBy('created_at', 'desc')->get();
        } else {
            $projects = collect(); 
        }

        return Inertia::render('admin/InvoiceManagement', [
            'clients' => $clients,
            'projects' => $projects,
        ]);
    }
}
