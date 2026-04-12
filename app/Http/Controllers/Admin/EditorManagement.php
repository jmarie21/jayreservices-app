<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProjectsExport;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EditorManagement extends Controller
{
    public function index(Request $request)
    {
        $hasDateQuery = $request->query->has('date_from') || $request->query->has('date_to');
        $defaultDate = Carbon::today()->toDateString();

        $dateFrom = $hasDateQuery ? (string) $request->query('date_from', '') : $defaultDate;
        $dateTo = $hasDateQuery ? (string) $request->query('date_to', '') : $defaultDate;

        $editors = User::query()
            ->where('role', 'editor')
            ->orderBy('name')
            ->with([
                'assignedProjects' => function ($query) use ($dateFrom, $dateTo) {
                    $query->with([
                        'client:id,name',
                        'service.category.addonAssignments.addon',
                        'service.addonAssignments.addon',
                    ])->latest();

                    if ($dateFrom !== '') {
                        $query->whereDate('created_at', '>=', $dateFrom);
                    }

                    if ($dateTo !== '') {
                        $query->whereDate('created_at', '<=', $dateTo);
                    }
                },
            ])
            ->get(['id', 'name']);

        return Inertia::render('admin/EditorsProjects', [
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'editors' => $editors
                ->map(fn (User $editor) => [
                    'id' => $editor->id,
                    'name' => $editor->name,
                    'projects' => $editor->assignedProjects
                        ->map(fn (Project $project) => $this->transformProjectSummary($project))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function showEditorProjects(Request $request, User $editor)
    {
        $query = $editor->assignedProjects()
            ->with(['service', 'client', 'comments.user', 'comments.attachments'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('project_name', 'like', "%{$searchTerm}%")
                    ->orWhere('style', 'like', "%{$searchTerm}%")
                    ->orWhereHas('service', fn ($sq) => $sq->where('name', 'like', "%{$searchTerm}%"))
                    ->orWhereHas('client', fn ($sq) => $sq->where('name', 'like', "%{$searchTerm}%"));
            });
        }

        $projects = $query->paginate(10)->withQueryString();

        return Inertia::render('admin/EditorManagement', [
            'editor' => $editor->only(['id', 'name', 'email']),
            'projects' => $projects,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
        ]);
    }

    protected function transformProjectSummary(Project $project): array
    {
        return [
            'client_name' => $project->client?->name ?? 'N/A',
            'project_name' => $project->project_name,
            'service' => $project->service?->name ?? 'N/A',
            'video_format' => ProjectsExport::formatVideoFormat($project),
            'add_ons' => ProjectsExport::formatAddOns($project),
            'priority' => $project->priority,
            'total_price' => $project->total_price !== null ? (float) $project->total_price : null,
            'editor_price' => $project->editor_price !== null ? (float) $project->editor_price : null,
            'created_at' => $project->created_at?->toISOString() ?? Carbon::parse($project->created_at)->toISOString(),
        ];
    }
}
