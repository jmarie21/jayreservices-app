<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProjectSentToClientMail;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Notifications\ClientProjectStatusNotification;
use App\Notifications\ProjectAssignedNotification;
use App\Notifications\ProjectRevisionNotification;
use App\Notifications\ProjectStatusNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            'output_link' => 'nullable|array',
            'output_link.*.name' => 'nullable|string',
            'output_link.*.link' => 'nullable|string',
            'priority' => 'nullable|in:urgent,high,normal,low',
        ]);

        if (!empty($validated['output_link'])) {
            $validated['output_link'] = array_map(function ($item) {
                if (is_array($item) && !empty($item['link']) && !preg_match('/^https?:\/\//', $item['link'])) {
                    $item['link'] = 'https://' . $item['link'];
                }
                return $item;
            }, $validated['output_link']);
        }

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

        // 🔔 Notify assigned editor if status changed to "revision" or "done_qa"
        if (
            isset($validated['status']) &&
            in_array(strtolower($validated['status']), ['revision', 'done_qa']) &&
            strtolower($oldStatus) !== strtolower($validated['status'])
        ) {
            $editor = $project->editor;
            if ($editor) {
                $status = strtolower($validated['status']) === 'revision' ? 'for_revision' : 'done_qa';
                $editor->notify(new ProjectStatusNotification($project, $status, 'admin'));
            }
        }



        // ✅ Send email if status changed to "sent_to_client"
        if (
            isset($validated['status']) &&
            strtolower($validated['status']) === 'sent_to_client' &&
            strtolower($oldStatus) !== 'sent_to_client'
        ) {
            if ($project->client) {
                $recipients = $project->client->getAllEmails();

                if (!empty($recipients)) {
                    Mail::to($recipients)->queue(new ProjectSentToClientMail($project));
                }

                // ✅ Send notification to client when project is sent to them
                $client = $project->client;

                // Check for duplicate notification (within last 5 mins)
                $recentClientNotification = $client->notifications()
                    ->where('type', ClientProjectStatusNotification::class)
                    ->where('data->project_id', $project->id)
                    ->where('data->status', 'sent_to_client')
                    ->where('created_at', '>', now()->subMinutes(5))
                    ->exists();

                if (!$recentClientNotification) {
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

    public function realEstateServices()
    {
        $services = Service::where('name', 'like', 'Real Estate%')->get();

        return Inertia::render("admin/Services", [
            "services" => $services
        ]);
    }

    public function weddingServices()
    {
        $services = Service::where('name', 'like', 'Wedding%')->get();

        return Inertia::render("admin/WeddingServices", [
            "services" => $services
        ]);
    }

    public function eventServices()
    {
        $services = Service::where('name', 'like', 'Event%')->get();

        return Inertia::render("admin/EventServices", [
            "services" => $services
        ]);
    }

    public function constructionServices()
    {
        $services = Service::where('name', 'like', 'Construction%')->get();

        return Inertia::render("admin/ConstructionServices", [
            "services" => $services
        ]);
    }

    public function talkingHeadsServices()
    {
        $services = Service::where('name', 'like', 'Talking Heads%')->get();

        return Inertia::render("admin/TalkingHeadsServices", [
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
            "output_link" => ['nullable', 'array'],
            "output_link.*.name" => ['nullable', 'string'],
            "output_link.*.link" => ['nullable', 'string'],
            "status" => ['nullable', 'in:pending,in_progress,completed'],
            "extra_fields" => ['nullable', 'array'],
            "with_agent" => ['nullable', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "per_property_count" => ['nullable', 'integer'],
            "rush" => ['required', 'boolean'],
        ]);

        if (!empty($validated['output_link'])) {
            $validated['output_link'] = array_map(function ($item) {
                if (is_array($item) && !empty($item['link']) && !preg_match('/^https?:\/\//', $item['link'])) {
                    $item['link'] = 'https://' . $item['link'];
                }
                return $item;
            }, $validated['output_link']);
        }

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
        if (!empty($validated['per_property']) && !empty($validated['per_property_count'])) {
            $qty = max(1, (int) $validated['per_property_count']);
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
            'per_property' => $validated['per_property'] ?? false,
            'per_property_count' => $validated['per_property_count'] ?? 0,
            'rush' => $validated['rush'] ?? false,
            'computed_editor_price' => $editorPrice,
        ]);

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
            "output_link" => ['nullable', 'array'],
            "output_link.*.name" => ['nullable', 'string'],
            "output_link.*.link" => ['nullable', 'string'],
            "status" => ['nullable', 'in:pending,in_progress,completed'],
            "extra_fields" => ['nullable', 'array'],
            "with_agent" => ['nullable', 'boolean'],
            "per_property" => ['nullable', 'boolean'],
            "per_property_count" => ['nullable', 'integer'],
            "rush" => ['required', 'boolean'],
        ]);

        if (!empty($validated['output_link'])) {
            $validated['output_link'] = array_map(function ($item) {
                if (is_array($item) && !empty($item['link']) && !preg_match('/^https?:\/\//', $item['link'])) {
                    $item['link'] = 'https://' . $item['link'];
                }
                return $item;
            }, $validated['output_link']);
        }

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
        if (!empty($validated['per_property']) && !empty($validated['per_property_count'])) {
            $qty = max(1, (int) $validated['per_property_count']);
            $editorPrice += 100 * $qty;
        }


        // =====================
        // SAVE RESULT
        // =====================
        $validated['editor_price'] = $editorPrice;

        Log::info('🧮 Editor Price Debug (Update)', [
            'style' => $style,
            'format' => $format,
            'effects' => $effectsRaw,
            'captions' => $captions,
            'with_agent' => $validated['with_agent'] ?? false,
            'per_property' => $validated['per_property'] ?? false,
            'per_property_count' => $validated['per_property_count'] ?? 0,
            'rush' => $validated['rush'] ?? false,
            'computed_editor_price' => $editorPrice,
        ]);

        $project->update($validated);

        return back()->with('message', 'Project updated successfully!');
    }
}
