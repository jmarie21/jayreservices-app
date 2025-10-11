<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CommentController extends Controller
{
    public function store(Request $request, Project $project)
    {
        // Debug logging
        Log::info('Comment submission received');
        Log::info('Request all:', $request->all());
        Log::info('Has file (image):', ['hasFile' => $request->hasFile('image')]);
        Log::info('All files:', $request->allFiles());

        $request->validate([
            'body' => 'nullable|string|max:2000',
            'image' => 'nullable|file|image|max:5120', // max 5MB
        ]);

        // Reject if both text and image are empty
        if (!$request->body && !$request->hasFile('image')) {
            return back()->withErrors(['body' => 'Comment cannot be empty.'])->withInput();
        }

        $path = null;

        if ($request->hasFile('image')) {
            Log::info('Image file found, attempting upload...');
            
            // store in s3/chat-comments/ and just get the key
            $path = $request->file('image')->store("chat-comments", "s3");
            
            Log::info('Upload successful, path:', ['path' => $path]);
        } else {
            Log::warning('No image file detected in request');
        }

        $comment = $project->comments()->create([
            'user_id'   => auth()->id(),
            'body'      => $request->body,
            'image_url' => $path, // <-- only the key (e.g., "chat-comments/abc.png")
        ]);

        $comment->load('user');

        return back()->with('newComment', $comment);
    }

    
}
