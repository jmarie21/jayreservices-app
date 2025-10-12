<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\NewProjectNotification;
use App\Models\Project;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ProjectsController extends Controller
{

    public function index(Request $request)
    {
        $query = Auth::user()
        ->projects()
        ->with(['service', 'comments.user'])
        ->latest();

        // Status mapping for client
        $clientStatusGroups = [
            'pending' => ['todo', 'backlog'],
            'in_progress' => ['in_progress', 'for_qa', 'done_qa', 'revision'],
            'completed' => ['revision_completed', 'sent_to_client'],
        ];

        if ($request->filled('status')) {
            $status = $request->status;

            if (in_array($status, array_keys($clientStatusGroups))) {
                // Client filter
                $query->whereIn('status', $clientStatusGroups[$status]);
            } else {
                // Admin filter (raw status)
                $query->where('status', $status);
            }
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // 🔍 Search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('project_name', 'like', "%{$searchTerm}%")
                ->orWhere('style', 'like', "%{$searchTerm}%")
                ->orWhereHas('service', function ($sq) use ($searchTerm) {
                    $sq->where('name', 'like', "%{$searchTerm}%");
                });
            });
        }

        $projects = $query->paginate(10)->withQueryString();

        return Inertia::render("client/Projects", [
            "projects" => $projects,
            "filters"  => $request->only(['status', 'date_from', 'date_to', 'search']),
        ]);
    }

    public function createProject(Request $request)
    {
        $validated = $request->validate([
            "service_id" => ['required', 'exists:services,id'],
            "style" => ['required', 'string'],
            "project_name" => ['required', 'string'],
            "format" => ['nullable', 'string'],
            "camera" => ['nullable', 'string'],
            "quality" => ['nullable', 'string'],
            "music" => ['nullable', 'string'],
            "music_link" => ['nullable', 'string'],
            "file_link" => ['required', 'string'],
            "notes" => ['nullable', 'string'],
            "total_price" => ['required', 'numeric'],
            "output_link" => ['nullable', 'string'],
            "status" => ['nullable', 'in:pending,in_progress,completed'],
            "extra_fields" => ['nullable', 'array'],
            "with_agent" => ['required', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "rush" => ['nullable', 'boolean'],
        ]);

        $validated['client_id'] = Auth::id(); 
        $validated['status'] = $validated['status'] ?? 'pending';

        $project = Project::create($validated);

        // 📧 Send email notification to admin
        // Mail::to('storageestate21@gmail.com')->send(new NewProjectNotification($project));

        return redirect(route("projects"))->with('message', 'Order placed successfully!');
    }

    public function updateProject(Request $request, Project $project)
    {
        // Authorize if needed (to make sure the client owns this project)
        if ($project->client_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            "service_id" => ['required', 'exists:services,id'],
            "style" => ['required', 'string'],
            "project_name" => ['required', 'string'],
            "format" => ['nullable', 'string'],
            "camera" => ['nullable', 'string'],
            "quality" => ['nullable', 'string'],
            "music" => ['nullable', 'string'],
            "music_link" => ['nullable', 'string'],
            "file_link" => ['required', 'string'],
            "notes" => ['nullable', 'string'],
            "total_price" => ['required', 'numeric'],
            "output_link" => ['nullable', 'string'],
            "status" => ['nullable', 'in:pending,in_progress,completed'],
            "extra_fields" => ['nullable', 'array'],
            "with_agent" => ['required', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "rush" => ['nullable', 'boolean'],
        ]);

        $project->update($validated);

        return redirect()->back()->with('message', 'Project updated successfully!');
    }

    public function updateStatus(Request $request, Project $project)
    {
        if ($project->client_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            "status" => ['required', 'in:todo,in_progress,for_qa,done_qa,sent_to_client,revision,revision_completed,backlog'],
        ]);

        $project->update(['status' => $validated['status']]);

        return back()->with('message', 'Project status updated successfully!');
    }


}
