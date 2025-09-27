<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class EditorProjectsController extends Controller
{
    public function index(Request $request)
    {
        $editor = $request->user();

        if ($editor->role !== 'editor') {
            abort(403, 'Unauthorized');
        }

        $filters = $request->only(['status', 'date_from', 'date_to', 'search']);

        $projects = Project::with(['client', 'service', 'comments.user'])
            ->where('editor_id', $editor->id)
            ->when($filters['status'] ?? null, fn($q, $status) => $q->where('status', $status))
            ->when($filters['date_from'] ?? null, fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('project_name', 'like', "%$search%")
                  ->orWhereHas('client', fn($q) => $q->where('name', 'like', "%$search%"));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('editor/EditorProjects', [
            'editor' => $editor,
            'projects' => $projects,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        // Ensure only editors assigned to the project can update it
        if ($request->user()->id !== $project->editor_id) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'editor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
            'output_link' => 'nullable|string',
        ]);

        if (!empty($validated['output_link']) && !preg_match('/^https?:\/\//', $validated['output_link'])) {
            $validated['output_link'] = 'https://' . $validated['output_link'];
        }

        $project->update($validated);

        return back()->with('success', 'Project updated successfully.');
    }

}
