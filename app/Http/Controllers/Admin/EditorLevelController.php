<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignEditorLevelRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EditorLevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/EditorLevels', [
            'editors' => User::where('role', 'editor')->get(['id', 'name', 'email', 'editor_level']),
        ]);
    }

    public function assign(AssignEditorLevelRequest $request): RedirectResponse
    {
        User::whereIn('id', $request->validated('user_ids'))
            ->update(['editor_level' => $request->validated('level')]);

        return back();
    }
}
