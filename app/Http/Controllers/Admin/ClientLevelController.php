<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignClientLevelRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClientLevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ClientLevels', [
            'clients' => User::where('role', 'client')->get(['id', 'name', 'email', 'recommended_editor_level']),
        ]);
    }

    public function assign(AssignClientLevelRequest $request): RedirectResponse
    {
        User::whereIn('id', $request->validated('user_ids'))
            ->update(['recommended_editor_level' => $request->validated('level')]);

        return back();
    }
}
