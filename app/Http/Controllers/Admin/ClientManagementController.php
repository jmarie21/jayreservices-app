<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ClientManagementController extends Controller
{
    public function index(): Response
    {
        $clients = User::where('role', 'client')
            ->withCount(['dedicatedEditorRules', 'extraRequests'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/ClientManagement', [
            'clients' => $clients->map(fn (User $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'recommended_editor_level' => $client->recommended_editor_level,
                'dedicated_editor_rules_count' => $client->dedicated_editor_rules_count,
                'extra_requests_count' => $client->extra_requests_count,
            ])->values(),
        ]);
    }

    public function show(User $client): Response
    {
        $client->loadCount('dedicatedEditorRules');
        $client->load(['extraRequests' => fn ($query) => $query->latest()]);

        return Inertia::render('admin/ClientManagementShow', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'recommended_editor_level' => $client->recommended_editor_level,
                'dedicated_editor_rules_count' => $client->dedicated_editor_rules_count,
                'extra_requests' => $client->extraRequests,
            ],
        ]);
    }
}
