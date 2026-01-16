<?php

namespace App\Http\Controllers\Client;

use App\Events\NewProjectCreatedEvent;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Notifications\NewProjectCreatedNotification;
use App\Notifications\ProjectRevisionNotification;
use App\Notifications\ProjectStatusNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ProjectsController extends Controller
{

    public function index(Request $request)
    {
        try {
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

            Log::info('Client viewed projects', [
                'user_id' => Auth::id(),
                'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
                'total_results' => $projects->total(),
            ]);

            return Inertia::render("client/Projects", [
                "projects" => $projects,
                "filters"  => $request->only(['status', 'date_from', 'date_to', 'search']),
                "viewProjectId" => $request->query('view') ? (int) $request->query('view') : null,
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

    public function createProject(Request $request)
    {
        try {
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
            "output_link" => ['nullable', 'array'],
            "output_link.*.name" => ['nullable', 'string'],
            "output_link.*.link" => ['nullable', 'string'],
            "status" => ['nullable', 'in:pending,in_progress,completed'],
            "extra_fields" => ['nullable', 'array'],
            "with_agent" => ['nullable', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "per_property_count" => ['nullable', 'integer'],
            "rush" => ['nullable', 'boolean'],
        ]);

        $validated['client_id'] = Auth::id();
        $validated['status'] = $validated['status'] ?? 'pending';

        $validated['priority'] = $validated['rush'] ? 'urgent' : null;

        // 🧮 Base Price Calculation
        $editorPrice = 0;
        $style = strtolower(trim($validated['style']));
        $format = strtolower(trim($validated['format'] ?? ''));
        $extra = $validated['extra_fields'] ?? [];
        $effectsRaw = $extra['effects'] ?? [];
        $captions = $extra['captions'] ?? [];

        // Normalize effects to array
        $effects = collect($effectsRaw)->map(fn($e) => is_array($e) ? $e['id'] : $e)->toArray();

        // =====================
        // BASE PRICES
        // =====================
        switch ($style) {
            // BASIC
            case 'basic video':
                $editorPrice = match ($format) {
                    'horizontal' => 500,
                    'vertical' => 350,
                    'horizontal and vertical package' => 850,
                    default => 0
                };
                break;

            case 'basic drone only':
                $editorPrice = match ($format) {
                    'horizontal' => 350,
                    'vertical' => 300,
                    'horizontal and vertical package' => 650,
                    default => 0
                };
                break;

            // DELUXE
            case 'deluxe video':
                $editorPrice = match ($format) {
                    'horizontal' => 1000,
                    'vertical' => 700,
                    'horizontal and vertical package' => 1700,
                    default => 0
                };
                break;

            case 'deluxe drone only':
                $editorPrice = match ($format) {
                    'horizontal' => 500,
                    'vertical' => 400,
                    'horizontal and vertical package' => 900,
                    default => 0
                };
                break;

            // PREMIUM
            case 'premium video':
                $editorPrice = match ($format) {
                    'horizontal' => 1500,
                    'vertical' => 1200,
                    'horizontal and vertical package' => 2700,
                    default => 0
                };
                break;

            case 'premium drone only':
                $editorPrice = match ($format) {
                    'horizontal' => 800,
                    'vertical' => 600,
                    'horizontal and vertical package' => 1400,
                    default => 0
                };
                break;

            // LUXURY
            case 'luxury video':
                $editorPrice = match ($format) {
                    'horizontal' => 1800,
                    'vertical' => 1500,
                    'horizontal and vertical package' => 3300,
                    default => 0
                };
                break;

            case 'luxury drone only':
                $editorPrice = match ($format) {
                    'horizontal' => 1000,
                    'vertical' => 800,
                    'horizontal and vertical package' => 1800,
                    default => 0
                };
                break;
        }

        // =====================
        // COMMON ADD-ONS
        // =====================

        if (!empty($validated['with_agent']) && $validated['with_agent']) {
            $editorPrice += 100;
        }

        // Rush fee
        if (!empty($validated['rush']) && $validated['rush']) {
            if (str_contains($style, 'premium') || str_contains($style, 'luxury')) {
                $editorPrice += 500;
            } else {
                $editorPrice += 200;
            }
        }

        // =====================
        // PREMIUM & LUXURY ADD-ONS
        // =====================

        if (str_contains($style, 'premium') || str_contains($style, 'luxury')) {
            // Captions while talking
            if (in_array('Captions while the agent is talking', $captions)) {
                $editorPrice += 200;
            }

            // 3D Text Behind Agent
            if (in_array('3D Text behind the Agent Talking', $captions)) {
                $editorPrice += 350;
            }
        }

        // =====================
        // LUXURY-SPECIFIC ADD-ONS
        // =====================
        if (str_contains($style, 'luxury')) {
            // 3D Track Text
            if (in_array('3D Text tracked on the ground etc.', $captions)) {
                $editorPrice += 400;
            }
        }

        // =====================
        // EFFECTS WITH QUANTITIES
        // =====================
        if (str_contains($style, 'premium') || str_contains($style, 'luxury')) {
            $effectQuantities = collect($effectsRaw)->mapWithKeys(function ($e) {
                if (is_array($e)) {
                    return [trim($e['id']) => (int)($e['quantity'] ?? 1)];
                }
                return [trim($e) => 1];
            });

            $perEffectPrices = [
                'Painting Transition' => 150,
                'Earth Zoom Transition' => 150,
                'Day to Night AI' => 150,
                'Virtual Staging AI' => 300,
            ];

            foreach ($perEffectPrices as $effectName => $price) {
                if ($effectQuantities->has($effectName)) {
                    $qty = $effectQuantities[$effectName];
                    $editorPrice += $price * max(1, $qty);
                }
            }
        }

        // =====================
        // PER PROPERTY LINE ADD-ON
        // =====================
        if (!empty($validated['per_property']) && isset($extra['per_property_quantity'])) {
            $qty = max(1, (int) $extra['per_property_quantity']);
            $editorPrice += 100 * $qty;
        }

        // =====================
        // SAVE RESULT
        // =====================
        $validated['editor_price'] = $editorPrice;

        Log::info('🧮 Editor Price Debug', [
            'style' => $style,
            'format' => $format,
            'effects' => $effectsRaw,
            'captions' => $captions,
            'with_agent' => $validated['with_agent'] ?? false,
            'rush' => $validated['rush'] ?? false,
            'computed_editor_price' => $editorPrice,
        ]);

        $project = Project::create($validated);

        Log::info('Project created', [
            'project_id' => $project->id,
            'project_name' => $project->project_name,
            'client_id' => Auth::id(),
            'service_id' => $project->service_id,
            'style' => $style,
            'total_price' => $project->total_price,
            'editor_price' => $editorPrice,
        ]);

        // 📧 Send email notification to admin
        // Mail::to('storageestate21@gmail.com')->send(new NewProjectNotification($project));

        // 🔔 Send in-app notification to all admins
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new NewProjectCreatedNotification($project));
        }

        // broadcast(new NewProjectCreatedEvent($project))->toOthers();

            return redirect(route("projects"))->with('message', 'Order placed successfully!');
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

    public function updateProject(Request $request, Project $project)
    {
        try {
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
            "output_link" => ['nullable', 'array'],
            "output_link.*.name" => ['nullable', 'string'],
            "output_link.*.link" => ['nullable', 'string'],
            "status" => ['nullable', 'in:pending,in_progress,completed'],
            "extra_fields" => ['nullable', 'array'],
            "with_agent" => ['nullable', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "per_property_count" => ['nullable', 'integer'],
            "rush" => ['nullable', 'boolean'],
        ]);

        $validated['priority'] = $validated['rush'] ? 'urgent' : null;

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
            // Only the project client can trigger this
            if ($project->client_id !== Auth::id()) {
                abort(403, 'Unauthorized');
            }

            $validated = $request->validate([
            "status" => ['required', 'in:todo,in_progress,for_qa,done_qa,sent_to_client,revision,revision_completed,backlog'],
        ]);

        $newStatus = strtolower($validated['status']);
        $oldStatus = $project->status;
        $project->update(['status' => $newStatus]);

        Log::info('Project status updated', [
            'project_id' => $project->id,
            'project_name' => $project->project_name,
            'client_id' => Auth::id(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        // Define statuses that trigger notifications
        $notifiableStatuses = ['revision', 'for_qa', 'done_qa', 'revision_completed', 'sent_to_client'];

        if (in_array($newStatus, $notifiableStatuses, true)) {
            // 🔔 Notify all admins
            $admins = User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $recentNotification = $admin->notifications()
                    ->where('type', ProjectStatusNotification::class)
                    ->where('data->project_id', $project->id)
                    ->where('data->status', $newStatus)
                    ->where('created_at', '>', now()->subMinutes(5))
                    ->exists();

                if (!$recentNotification) {
                    $admin->notify(new ProjectStatusNotification($project, $newStatus, 'client'));
                }
            }

            // 🔔 Notify assigned editor (if any)
            if ($project->editor_id) {
                $editor = User::find($project->editor_id);

                if ($editor) {
                    $recentNotification = $editor->notifications()
                        ->where('type', ProjectStatusNotification::class)
                        ->where('data->project_id', $project->id)
                        ->where('data->status', $newStatus)
                        ->where('created_at', '>', now()->subMinutes(5))
                        ->exists();

                    if (!$recentNotification) {
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
}
