<?php

namespace App\Http\Controllers\Client;

use App\Events\NewProjectCreatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SupportConversation;
use App\Models\User;
use App\Notifications\NewProjectCreatedNotification;
use App\Notifications\ProjectStatusNotification;
use App\Services\PricingService;
use App\Services\SupportChatBroadcaster;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ProjectsController extends Controller
{
    public function index(Request $request, PricingService $pricingService): Response
    {
        try {
            $query = Auth::user()
                ->projects()
                ->with(['service', 'comments.user', 'comments.attachments'])
                ->latest();

            $clientStatusGroups = [
                'pending' => ['todo', 'backlog'],
                'in_progress' => ['in_progress', 'for_qa', 'done_qa', 'revision'],
                'completed' => ['revision_completed', 'sent_to_client'],
                'cancelled' => ['cancelled'],
            ];

            if ($request->filled('status')) {
                $status = $request->status;

                if (array_key_exists($status, $clientStatusGroups)) {
                    $query->whereIn('status', $clientStatusGroups[$status]);
                } else {
                    $query->where('status', $status);
                }
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
                        ->orWhereHas('service', function ($sq) use ($searchTerm) {
                            $sq->where('name', 'like', "%{$searchTerm}%");
                        });
                });
            }

            $projects = $query->paginate(10)->withQueryString();

            $this->attachServicePricingData($projects, $pricingService);

            Log::info('Client viewed projects', [
                'user_id' => Auth::id(),
                'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
                'total_results' => $projects->total(),
            ]);

            return Inertia::render('client/Projects', [
                'projects' => $projects,
                'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
                'viewProjectId' => $request->query('view') ? (int) $request->query('view') : null,
            ])->withViewData(['ssr' => false]);
        } catch (\Exception $e) {
            Log::error('Error loading client projects', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
            ]);

            throw $e;
        }
    }

    public function createProject(Request $request, PricingService $pricingService)
    {
        try {
            $validated = $request->validate($this->projectRules());
            $validated['client_id'] = Auth::id();
            $validated['status'] = $validated['status'] ?? 'pending';

            $validated = $this->prepareProjectPayload($validated, $pricingService);
            $project = Project::create($validated);

            Log::info('Project created', [
                'project_id' => $project->id,
                'project_name' => $project->project_name,
                'client_id' => Auth::id(),
                'service_id' => $project->service_id,
                'style' => $project->style,
                'total_price' => $project->total_price,
                'editor_price' => $project->editor_price,
            ]);

            $admins = User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $admin->notify(new NewProjectCreatedNotification($project));
            }

            // broadcast(new NewProjectCreatedEvent($project))->toOthers();
            event(new NewProjectCreatedEvent($project));

            return redirect(route('projects'))->with('message', 'Order placed successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Project creation validation failed', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
                'input' => $request->except(['file_link']),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error creating project', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['file_link']),
            ]);

            throw $e;
        }
    }

    public function updateProject(Request $request, Project $project, PricingService $pricingService)
    {
        try {
            if ($project->client_id !== Auth::id()) {
                abort(403, 'Unauthorized');
            }

            $validated = $request->validate($this->projectRules());
            $validated = $this->prepareProjectPayload($validated, $pricingService);

            $project->update($validated);

            Log::info('Project updated', [
                'project_id' => $project->id,
                'project_name' => $project->project_name,
                'client_id' => Auth::id(),
                'updated_fields' => array_keys($validated),
            ]);

            return redirect()->back()->with('message', 'Project updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Project update validation failed', [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error updating project', [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function updateStatus(Request $request, Project $project)
    {
        try {
            if ($project->client_id !== Auth::id()) {
                abort(403, 'Unauthorized');
            }

            $validated = $request->validate([
                'status' => ['required', 'in:todo,in_progress,for_qa,done_qa,sent_to_client,revision,revision_completed,backlog'],
            ]);

            $newStatus = strtolower($validated['status']);
            $oldStatus = $project->status;

            $updateData = ['status' => $newStatus];

            if ($newStatus === 'revision') {
                $updateData['revision_since'] = now();
            }

            $project->update($updateData);

            if ($newStatus === 'revision') {
                $this->sendRevisionChatMessage($request->user(), $project);
            }

            Log::info('Project status updated', [
                'project_id' => $project->id,
                'project_name' => $project->project_name,
                'client_id' => Auth::id(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ]);

            $notifiableStatuses = ['revision', 'for_qa', 'done_qa', 'revision_completed', 'sent_to_client'];

            if (in_array($newStatus, $notifiableStatuses, true)) {
                $admins = User::where('role', 'admin')->get();

                foreach ($admins as $admin) {
                    $recentNotification = $admin->notifications()
                        ->where('type', ProjectStatusNotification::class)
                        ->where('data->project_id', $project->id)
                        ->where('data->status', $newStatus)
                        ->where('created_at', '>', now()->subMinutes(5))
                        ->exists();

                    if (! $recentNotification) {
                        $admin->notify(new ProjectStatusNotification($project, $newStatus, 'client'));
                    }
                }

                if ($project->editor_id) {
                    $editor = User::find($project->editor_id);

                    if ($editor) {
                        $recentNotification = $editor->notifications()
                            ->where('type', ProjectStatusNotification::class)
                            ->where('data->project_id', $project->id)
                            ->where('data->status', $newStatus)
                            ->where('created_at', '>', now()->subMinutes(5))
                            ->exists();

                        if (! $recentNotification) {
                            $editor->notify(new ProjectStatusNotification($project, $newStatus, 'client'));
                        }
                    }
                }
            }

            return back()->with('message', 'Project status updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Project status update validation failed', [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Error updating project status', [
                'project_id' => $project->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    protected function projectRules(): array
    {
        return [
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

    protected function attachServicePricingData(LengthAwarePaginator $projects, PricingService $pricingService): void
    {
        $pricingCache = [];

        $projects->getCollection()->transform(function (Project $project) use ($pricingService, &$pricingCache) {
            if ($project->service) {
                $pricingCache[$project->service_id] ??= $pricingService->getServicePricingData($project->service_id);
                $project->service->setAttribute('pricing_data', $pricingCache[$project->service_id]);
            }

            return $project;
        });
    }

    protected function sendRevisionChatMessage(User $client, Project $project): void
    {
        try {
            $conversation = SupportConversation::query()->firstOrCreate([
                'client_id' => $client->id,
            ]);

            $serviceName = $project->service?->name ?? 'Unknown Service';
            $body = "Hi, I'd like to request a revision for my project \"{$project->project_name}\" ({$serviceName}). Please check the comments for details.";

            $message = $conversation->messages()->create([
                'sender_id' => $client->id,
                'body' => $body,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
                'last_message_sender_id' => $client->id,
                'client_last_read_at' => $message->created_at,
            ])->save();

            $broadcaster = app(SupportChatBroadcaster::class);
            $conversation = SupportConversation::query()
                ->withSupportSummaryData()
                ->with(['messages.sender:id,name,role', 'messages.attachments'])
                ->findOrFail($conversation->id);

            $message->load(['sender:id,name,role', 'attachments']);

            $broadcaster->dispatch($conversation, $message);
        } catch (\Throwable $e) {
            Log::warning('Failed to send revision chat message', [
                'project_id' => $project->id,
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
