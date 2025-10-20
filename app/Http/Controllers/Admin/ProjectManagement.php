<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProjectSentToClientMail;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ProjectAssignedNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ProjectManagement extends Controller
{
    public function showClientProjects(Request $request, User $client)
    {
        $query = $client->projects()
            ->with(['service', 'editor', 'comments.user'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by editor
        if ($request->filled('editor_id')) {
            if ($request->editor_id === 'unassigned') {
                $query->whereNull('editor_id');
            } else {
                $query->where('editor_id', $request->editor_id);
            }
        }

        // Filter by date range - FIXED with proper timezone handling
        if ($request->filled('date_from') && $request->filled('date_to')) {
            // Parse dates and set proper time boundaries
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        } elseif ($request->filled('date_from')) {
            // Only date_from is provided
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $query->where('created_at', '>=', $dateFrom);
        } elseif ($request->filled('date_to')) {
            // Only date_to is provided
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->where('created_at', '<=', $dateTo);
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
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search', 'editor_id']),
            'editors' => $editors,
        ]);
    }

    public function showAllProjects(Request $request)
    {
        $query = Project::with(['client', 'service', 'editor', 'comments.user'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by editor
        if ($request->filled('editor_id')) {
            if ($request->editor_id === 'unassigned') {
                $query->whereNull('editor_id');
            } else {
                $query->where('editor_id', $request->editor_id);
            }
        }

        // Filter by date range - FIXED with proper timezone handling
        if ($request->filled('date_from') && $request->filled('date_to')) {
            // Parse dates and set proper time boundaries
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        } elseif ($request->filled('date_from')) {
            // Only date_from is provided
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $query->where('created_at', '>=', $dateFrom);
        } elseif ($request->filled('date_to')) {
            // Only date_to is provided
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->where('created_at', '<=', $dateTo);
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
        $projects = $query->paginate(20)->withQueryString();

        $editors = User::where('role', 'editor')->get(['id', 'name']);
        $clients = User::where('role', 'client')->get(['id', 'name']);

        return Inertia::render("admin/AllProjects", [
            'projects' => $projects,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search', 'editor_id']),
            'editors' => $editors,
            'clients' => $clients,
            'viewProjectId' => $request->query('view') ? (int) $request->query('view') : null, // 👈 Add this
        ]);
    }



    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'editor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
            'editor_price' => 'nullable|numeric|min:0',
            'output_link' => 'nullable|string',
            'priority' => 'nullable|in:urgent,high,normal,low',
        ]);

        $oldStatus = $project->status;
        $oldEditorId = $project->editor_id;

        $project->update($validated);

        // 🔔 Notify editor if newly assigned
        if (
            isset($validated['editor_id']) && 
            $validated['editor_id'] !== null && 
            $validated['editor_id'] !== $oldEditorId
        ) {
            $editor = User::find($validated['editor_id']);
            if ($editor) {
                $editor->notify(new ProjectAssignedNotification($project));
            }
        }


        // ✅ Send email if status changed to "sent_to_client"
        // if (
        //     isset($validated['status']) &&
        //     strtolower($validated['status']) === 'sent_to_client' &&
        //     strtolower($oldStatus) !== 'sent_to_client'
        // ) {
        //     if ($project->client) {
        //         $recipients = $project->client->getAllEmails();

        //         if (!empty($recipients)) {
        //             Mail::to($recipients)->queue(new ProjectSentToClientMail($project));
        //         }
        //     }
        // }


        return back()->with('success', 'Project updated successfully.');
    }

    public function updatePrice(Request $request, Project $project)
    {
        $validated = $request->validate([
            'total_price' => ['required', 'numeric', 'min:0'],
        ]);

        $project->update([
            'total_price' => $validated['total_price'],
        ]);

        return back()->with('success', 'Price updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return back()->with('success', 'Project deleted successfully.');
    }

    public function services()
    {
        $services = Service::all();

        return Inertia::render("admin/Services", [
            "services" => $services
        ]);
    }


    public function adminCreateProject(Request $request)
    {
        $validated = $request->validate([
            "client_id" => ['required', 'exists:users,id'],
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
            "with_agent" => ['nullable', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "rush" => ['required', 'boolean'],
        ]);

        $validated['status'] = $validated['status'] ?? 'pending';

        $validated['priority'] = $validated['rush'] ? 'urgent' : null;

        Project::create($validated);

        return back()->with('message', 'Project created successfully!');
    }

    public function adminUpdateProject(Request $request, Project $project)
    {
        $validated = $request->validate([
            "client_id" => ['required', 'exists:users,id'],
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
            "with_agent" => ['nullable', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "rush" => ['required', 'boolean'],
        ]);

        $validated['priority'] = $validated['rush'] ? 'urgent' : null;

        $project->update($validated);

        return back()->with('message', 'Project updated successfully!');
    }
}
