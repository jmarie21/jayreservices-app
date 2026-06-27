<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignClientLevelRequest;
use App\Http\Requests\Admin\UpdateDedicatedEditorRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientLevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ClientLevels', [
            'clients' => User::where('role', 'client')->get(['id', 'name', 'email', 'recommended_editor_level', 'dedicated_editor_id']),
            'editors' => User::where('role', 'editor')->get(['id', 'name']),
        ]);
    }

    public function assign(AssignClientLevelRequest $request): RedirectResponse
    {
        User::whereIn('id', $request->validated('user_ids'))
            ->update(['recommended_editor_level' => $request->validated('level')]);

        return back();
    }

    public function updateDedicatedEditor(UpdateDedicatedEditorRequest $request, User $client): RedirectResponse
    {
        $client->update(['dedicated_editor_id' => $request->validated('editor_id')]);

        return back();
    }
}
