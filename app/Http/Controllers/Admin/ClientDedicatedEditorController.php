<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateClientDedicatedEditorsRequest;
use App\Models\ClientDedicatedEditor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientDedicatedEditorController extends Controller
{
    public function edit(User $client): Response
    {
        $rules = ClientDedicatedEditor::where('client_id', $client->id)->get(['service_id', 'editor_id']);

        return Inertia::render('admin/ClientDedicatedEditors', [
            'client' => $client->only(['id', 'name', 'email']),
            'editors' => User::where('role', 'editor')->get(['id', 'name']),
            'services' => Service::with('category:id,name')
                ->orderBy('service_category_id')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'service_category_id']),
            'generalEditorIds' => $rules->whereNull('service_id')->pluck('editor_id')->values(),
            'serviceDedications' => $rules->whereNotNull('service_id')
                ->groupBy('service_id')
                ->map(fn ($group, $serviceId) => [
                    'service_id' => (int) $serviceId,
                    'editor_ids' => $group->pluck('editor_id')->values(),
                ])
                ->values(),
        ]);
    }

    public function update(UpdateClientDedicatedEditorsRequest $request, User $client): RedirectResponse
    {
        $serviceId = $request->validated('service_id');

        ClientDedicatedEditor::where('client_id', $client->id)
            ->where('service_id', $serviceId)
            ->delete();

        foreach ($request->validated('editor_ids') ?? [] as $editorId) {
            ClientDedicatedEditor::create([
                'client_id' => $client->id,
                'service_id' => $serviceId,
                'editor_id' => $editorId,
            ]);
        }

        return back();
    }
}
