<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $comment = $project->comments()->create([
            'user_id' => auth()->id(),
            'body' => $request->body,
        ]);

        $comment->load('user');

        // return back to Inertia with flash (for Inertia) and JSON fallback (if API call)
        return back()->with('newComment', $comment);
    }
}
