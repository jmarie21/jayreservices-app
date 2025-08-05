<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProjectsController extends Controller
{

    public function index()
    {
        $projects =Auth::user()->projects()->latest()->get();

        return Inertia::render("client/Projects", [
            "projects" => $projects
        ]);
    }

    public function createProject(Request $request)
    {
        $validated = $request->validate([
            "service_id" => ['required', 'exists:services,id'],
            "style" => ['required', 'string'],
            "company_name" => ['required', 'string'],
            "contact" => ['required', 'string'],
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
        ]);

        $validated['client_id'] = Auth::id(); 
        $validated['status'] = $validated['status'] ?? 'pending';

        Project::create($validated);

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
            "company_name" => ['required', 'string'],
            "contact" => ['required', 'string'],
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
        ]);

        $project->update($validated);

        return redirect()->back()->with('message', 'Project updated successfully!');
    }

}
