<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ProjectsExport;
use App\Http\Controllers\Controller;
use App\Mail\ProjectSentToClientMail;
use App\Models\Project;
use App\Models\ServiceCategory;
use App\Models\User;
use App\Notifications\ClientProjectStatusNotification;
use App\Notifications\ProjectAssignedNotification;
use App\Notifications\ProjectStatusNotification;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectManagement extends Controller
{
    public function showClientProjects(Request $request, User $client, PricingService $pricingService)
    {
        $query = $client->projects()
            ->with(['service', 'editor', 'comments.user', 'comments.attachments'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('editor_id')) {
            if ($request->editor_id === 'unassigned') {
                $query->whereNull('editor_id');
            } else {
                $query->where('editor_id', $request->editor_id);
            }
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();

            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        } elseif ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        } elseif ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('project_name', 'like', "%{$searchTerm}%")
                    ->orWhere('style', 'like', "%{$searchTerm}%")
                    ->orWhereHas('service', fn ($sq) => $sq->where('name', 'like', "%{$searchTerm}%"));
            });
        }

        $projects = $query->paginate(10)->withQueryString();
        $this->attachServicePricingData($projects, $pricingService, true);

        return Inertia::render('admin/ClientProjects', [
            'client' => $client->only(['id', 'name', 'email']),
            'projects' => $projects,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search', 'editor_id']),
            'editors' => User::where('role', 'editor')->get(['id', 'name']),
        ]);
    }

    public function showAllProjects(Request $request)
    {
        $query = Project::with(['client', 'service', 'editor', 'comments.user', 'comments.attachments'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('editor_id')) {
            if ($request->editor_id === 'unassigned') {
                $query->whereNull('editor_id');
            } else {
                $query->where('editor_id', $request->editor_id);
            }
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();

            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        } elseif ($request->filled('date_from')) {
            $query->where('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        } elseif ($request->filled('date_to')) {
            $query->where('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
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

        $projects = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/AllProjects', [
            'projects' => $projects,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search', 'editor_id']),
            'editors' => User::where('role', 'editor')->get(['id', 'name']),
            'clients' => User::where('role', 'client')->get(['id', 'name']),
            'viewProjectId' => $request->query('view') ? (int) $request->query('view') : null,
        ]);
    }

    public function previewExport(Request $request): \Illuminate\Http\JsonResponse
    {
        $export = new ProjectsExport(
            status: $request->input('status'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
            search: $request->input('search'),
            editorId: $request->input('editor_id'),
        );

        $projects = $export->query()->get();

        $data = $projects->map(fn (Project $project) => [
            'client' => $project->client?->name ?? 'N/A',
            'project_name' => $project->project_name,
            'service' => $project->service?->name ?? 'N/A',
            'video_format' => ProjectsExport::formatVideoFormat($project),
            'add_ons' => ProjectsExport::formatAddOns($project),
            'editor' => $project->editor?->name ?? 'Unassigned',
            'priority' => $project->priority,
            'total_price' => $project->total_price,
            'editor_price' => $project->editor_price,
            'created_at' => Carbon::parse($project->created_at)->format('Y-m-d H:i:s'),
        ]);

        return response()->json($data);
    }

    public function exportAllProjects(Request $request): BinaryFileResponse
    {
        $export = new ProjectsExport(
            status: $request->input('status'),
            dateFrom: $request->input('date_from'),
            dateTo: $request->input('date_to'),
            search: $request->input('search'),
            editorId: $request->input('editor_id'),
        );

        return Excel::download($export, 'projects-'.now()->format('Y-m-d').'.xlsx');
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'editor_id' => 'nullable|exists:users,id',
            'status' => 'nullable|string',
            'editor_price' => 'nullable|numeric|min:0',
            'output_link' => 'nullable|array',
            'output_link.*.name' => 'nullable|string',
            'output_link.*.link' => 'nullable|string',
            'priority' => 'nullable|in:urgent,high,normal,low',
        ]);

        if (! empty($validated['output_link'])) {
            $validated['output_link'] = array_map(function ($item) {
                if (is_array($item) && ! empty($item['link']) && ! preg_match('/^https?:\/\//', $item['link'])) {
                    $item['link'] = 'https://'.$item['link'];
                }

                return $item;
            }, $validated['output_link']);
        }

        $oldStatus = $project->status;
        $oldEditorId = $project->editor_id;

        $newStatus = $validated['status'] ?? null;

        if ($newStatus === 'in_progress' && $project->in_progress_since === null) {
            $validated['in_progress_since'] = now();
        }

        if ($newStatus !== null && $newStatus !== 'in_progress' && $project->status === 'in_progress') {
            $validated['in_progress_since'] = null;
        }

        if ($newStatus === 'revision') {
            $validated['revision_since'] = now();
        }

        if ($newStatus !== null && $newStatus !== 'revision' && $project->status === 'revision') {
            $validated['revision_since'] = null;
        }

        if (isset($validated['editor_id']) && $validated['editor_id'] !== $project->editor_id) {
            $validated['in_progress_since'] = now();
            $validated['revision_since'] = null;
            $validated['status'] = 'todo';
        }

        $project->update($validated);

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

        if (
            isset($validated['status']) &&
            in_array(strtolower($validated['status']), ['revision', 'done_qa'], true) &&
            strtolower($oldStatus) !== strtolower($validated['status'])
        ) {
            $editor = $project->editor;

            if ($editor) {
                $status = strtolower($validated['status']) === 'revision' ? 'for_revision' : 'done_qa';
                $editor->notify(new ProjectStatusNotification($project, $status, 'admin'));
            }
        }

        if (
            isset($validated['status']) &&
            strtolower($validated['status']) === 'sent_to_client' &&
            strtolower($oldStatus) !== 'sent_to_client' &&
            $project->client
        ) {
            $recipients = $project->client->getAllEmails();

            if (! empty($recipients)) {
                Mail::to($recipients)->queue(new ProjectSentToClientMail($project));
            }

            $client = $project->client;
            $recentClientNotification = $client->notifications()
                ->where('type', ClientProjectStatusNotification::class)
                ->where('data->project_id', $project->id)
                ->where('data->status', 'sent_to_client')
                ->where('created_at', '>', now()->subMinutes(5))
                ->exists();

            if (! $recentClientNotification) {
                $client->notify(new ClientProjectStatusNotification($project, 'sent_to_client'));

                Log::info('Client notified about project sent to client', [
                    'project_id' => $project->id,
                    'project_name' => $project->project_name,
                    'client_id' => $client->id,
                    'client_name' => $client->name,
                    'triggered_by' => 'admin',
                ]);
            }
        }

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

    public function servicesIndex(PricingService $pricingService)
    {
        return Inertia::render('admin/ServicesIndex', [
            'categories' => $pricingService->getAllCategoriesCatalogData(includeEditor: true),
        ]);
    }

    public function serviceCatalog(ServiceCategory $category, PricingService $pricingService)
    {
        abort_unless($category->is_active, 404);

        return Inertia::render('admin/ServiceCatalog', [
            'category' => $pricingService->getCategoryCatalogData($category, true),
            'clients' => User::where('role', 'client')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function realEstateServices()
    {
        return redirect()->route('admin.services.category', ['category' => 'real-estate']);
    }

    public function weddingServices()
    {
        return redirect()->route('admin.services.category', ['category' => 'wedding']);
    }

    public function eventServices()
    {
        return redirect()->route('admin.services.category', ['category' => 'event']);
    }

    public function constructionServices()
    {
        return redirect()->route('admin.services.category', ['category' => 'construction']);
    }

    public function talkingHeadsServices()
    {
        return redirect()->route('admin.services.category', ['category' => 'talking-heads']);
    }

    public function adminCreateProject(Request $request, PricingService $pricingService)
    {
        $validated = $request->validate($this->projectRules(includeClientId: true));
        $validated['status'] = $validated['status'] ?? 'pending';
        $validated = $this->prepareProjectPayload($validated, $pricingService);

        Project::create($validated);

        return back()->with('message', 'Project created successfully!');
    }

    public function adminUpdateProject(Request $request, Project $project, PricingService $pricingService)
    {
        $validated = $request->validate($this->projectRules(includeClientId: true));
        $validated = $this->prepareProjectPayload($validated, $pricingService);

        $project->update($validated);

        return back()->with('message', 'Project updated successfully!');
    }

    protected function projectRules(bool $includeClientId = false): array
    {
        $rules = [
            'service_id' => ['required', 'exists:services,id'],
            'service_sub_style_id' => ['nullable', 'exists:service_sub_styles,id'],
            'style' => ['nullable', 'string'],
            'company_name' => ['nullable', 'string'],
            'contact' => ['nullable', 'string'],
            'project_name' => ['required', 'string'],
            'format' => ['required', 'string'],
            'camera' => ['nullable', 'string'],
            'quality' => ['nullable', 'string'],
            'music' => ['nullable', 'string'],
            'music_link' => ['nullable', 'string'],
            'file_link' => ['required', 'string'],
            'notes' => ['nullable', 'string'],
            'total_price' => ['nullable', 'numeric'],
            'output_link' => ['nullable', 'array'],
            'output_link.*.name' => ['nullable', 'string'],
            'output_link.*.link' => ['nullable', 'string'],
            'status' => ['nullable', 'in:pending,in_progress,completed'],
            'extra_fields' => ['nullable', 'array'],
            'with_agent' => ['nullable', 'boolean'],
            'per_property' => ['nullable', 'boolean'],
            'per_property_count' => ['nullable', 'integer', 'min:0'],
            'rush' => ['nullable', 'boolean'],
        ];

        if ($includeClientId) {
            $rules['client_id'] = ['required', 'exists:users,id'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function prepareProjectPayload(array $validated, PricingService $pricingService): array
    {
        if (! empty($validated['output_link'])) {
            $validated['output_link'] = array_map(function ($item) {
                if (is_array($item) && ! empty($item['link']) && ! preg_match('/^https?:\/\//', $item['link'])) {
                    $item['link'] = 'https://'.$item['link'];
                }

                return $item;
            }, $validated['output_link']);
        }

        $validated['extra_fields'] = is_array($validated['extra_fields'] ?? null)
            ? $validated['extra_fields']
            : [];

        $serviceAddons = collect(Arr::get($validated, 'extra_fields.service_addons', []));
        $validated['with_agent'] = (bool) ($validated['with_agent']
            ?? $serviceAddons->contains(fn ($addon) => $this->submittedAddonMatchesRole((array) $addon, 'with-agent')));
        $validated['rush'] = (bool) ($validated['rush']
            ?? $serviceAddons->contains(fn ($addon) => $this->submittedAddonMatchesRole((array) $addon, 'rush')));
        $validated['per_property'] = (bool) ($validated['per_property']
            ?? $serviceAddons->contains(fn ($addon) => $this->submittedAddonMatchesRole((array) $addon, 'per-property-line')));

        if (! $validated['per_property']) {
            $validated['per_property_count'] = 0;
        } elseif (empty($validated['per_property_count'])) {
            $perPropertyAddon = $serviceAddons->first(fn ($addon) => $this->submittedAddonMatchesRole((array) $addon, 'per-property-line'));
            $validated['per_property_count'] = max(
                1,
                (int) ($perPropertyAddon['quantity'] ?? Arr::get($validated, 'extra_fields.per_property_quantity', 1))
            );
        }

        $pricing = $pricingService->calculatePrices($validated);

        $validated['service_sub_style_id'] = $pricing['sub_style_id'];
        $validated['style'] = $pricing['sub_style_name'] ?? ($validated['style'] ?? '');
        $validated['total_price'] = $pricing['client_total'];
        $validated['editor_price'] = $pricing['editor_total'];
        $validated['priority'] = $validated['rush'] ? 'urgent' : null;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $addon
     */
    protected function submittedAddonMatchesRole(array $addon, string $role): bool
    {
        return $this->normalizeAddonValue((string) ($addon['slug'] ?? '')) === $role
            || $this->normalizeAddonValue((string) ($addon['name'] ?? '')) === $role;
    }

    protected function normalizeAddonValue(?string $value): string
    {
        return str((string) $value)
            ->trim()
            ->lower()
            ->replace('&', 'and')
            ->replaceMatches('/[^a-z0-9]+/', '-')
            ->trim('-')
            ->value();
    }

    protected function attachServicePricingData(LengthAwarePaginator $projects, PricingService $pricingService, bool $includeEditor = false): void
    {
        $pricingCache = [];

        $projects->getCollection()->transform(function (Project $project) use ($pricingService, $includeEditor, &$pricingCache) {
            if ($project->service) {
                $cacheKey = $project->service_id.'|'.($includeEditor ? 'admin' : 'client');
                $pricingCache[$cacheKey] ??= $pricingService->getServicePricingData($project->service_id, $includeEditor);
                $project->service->setAttribute('pricing_data', $pricingCache[$cacheKey]);
            }

            return $project;
        });
    }
}
