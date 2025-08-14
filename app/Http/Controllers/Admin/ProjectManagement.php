<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectManagement extends Controller
{
    public function showClientProjects(Request $request, User $client)
    {
        $query = $client->projects()
            ->with(['service', 'editor'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Search
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('project_name', 'like', "%{$searchTerm}%")
                ->orWhere('style', 'like', "%{$searchTerm}%")
                ->orWhereHas('service', fn($sq) => $sq->where('name', 'like', "%{$searchTerm}%"));
            });
        }

        // Paginate results
        $projects = $query->paginate(10)->withQueryString();

        $editors = User::where('role', 'editor')->get(['id', 'name']);

        return Inertia::render("admin/ClientProjects", [
            'client' => $client->only(['id', 'name', 'email']),
            'projects' => $projects,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
            'editors' => $editors,
        ]);
    }


    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'editor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
        ]);

        $project->update($validated);

        return back()->with('success', 'Project updated successfully.');
    }

}
