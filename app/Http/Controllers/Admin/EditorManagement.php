<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EditorManagement extends Controller
{
    public function showEditorProjects(Request $request, User $editor)
{
    $query = $editor->assignedProjects()
        ->with(['service', 'client']) // load client + service
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
              ->orWhereHas('service', fn($sq) => $sq->where('name', 'like', "%{$searchTerm}%"))
              ->orWhereHas('client', fn($sq) => $sq->where('name', 'like', "%{$searchTerm}%"));
        });
    }

    // Paginate results
    $projects = $query->paginate(10)->withQueryString();

    return Inertia::render("admin/EditorManagement", [
        'editor' => $editor->only(['id', 'name', 'email']),
        'projects' => $projects,
        'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
    ]);
}

}
