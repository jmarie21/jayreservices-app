<?php

namespace App\Http\Controllers\Editor;

use App\Http\Controllers\Controller;
use App\Mail\ProjectSentToClientMail;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
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
            'viewProjectId' => $request->query('view') ? (int) $request->query('view') : null, // 👈 Add this
        ]);
    }

    public function update(Request $request, Project $project)
    {
        // Ensure only editors assigned to the project can update it
        if (
            $request->user()->role !== 'admin' &&
            $request->user()->id !== $project->editor_id
        ) {
            abort(403, 'Unauthorized');
        }


        $validated = $request->validate([
            'editor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
            'output_link' => 'nullable|string',
            'priority' => 'nullable|in:urgent,high,normal,low',
        ]);

        if (!empty($validated['output_link']) && !preg_match('/^https?:\/\//', $validated['output_link'])) {
            $validated['output_link'] = 'https://' . $validated['output_link'];
        }

        $oldStatus = $project->status;

        $project->update($validated);

        // ✅ Send email if status changed to "sent_to_client"
        if (
            isset($validated['status']) &&
            strtolower($validated['status']) === 'sent_to_client' &&
            strtolower($oldStatus) !== 'sent_to_client'
        ) {
            if ($project->client && $project->client->email) {
                Mail::to($project->client->email)->send(new ProjectSentToClientMail($project));
            }
        }

        return back()->with('success', 'Project updated successfully.');
    }

}
