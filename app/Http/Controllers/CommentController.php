<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\User;
use App\Notifications\ClientCommentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        $user = auth()->user();


        // 🔔 Send notification if comment is from client
        if (auth()->user()->role === 'client') {
            // Notify all admins
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                $admin->notify(new ClientCommentNotification($comment, $project));
            }

            // Notify assigned editor (if any)
            if ($project->editor_id) {
                $editor = User::find($project->editor_id);
                if ($editor) {
                    $editor->notify(new ClientCommentNotification($comment, $project));
                }
            }
        }

        if ($user->role === 'admin') {
            // Notify assigned editor only
            if ($project->editor_id) {
                $editor = User::find($project->editor_id);
                if ($editor) {
                    $editor->notify(new ClientCommentNotification($comment, $project));
                }
            }
        }

        // if ($user->role === 'editor') {
        //     // Notify all admins
        //     $admins = User::where('role', 'admin')->get();
        //     foreach ($admins as $admin) {
        //         $admin->notify(new ClientCommentNotification($comment, $project));
        //     }
        // }

        return back()->with('newComment', $comment);
    }

    public function update(Request $request, ProjectComment $comment)
    {
        // Only owner or admin can update
        if ($comment->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'image' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            // Optionally delete old image if exists
            if ($comment->image_url) {
                Storage::disk('s3')->delete($comment->image_url);
            }

            $path = $request->file('image')->store('comments', 's3');
            $validated['image_url'] = $path;
        }

        $comment->update([
            'body' => $validated['body'],
            'image_url' => $validated['image_url'] ?? $comment->image_url,
        ]);

        

        return back()->with('success', 'Comment updated successfully.');
    }

    /**
     * Delete a comment
     */
    public function destroy(ProjectComment $comment)
    {
        // Only owner or admin can delete
        if ($comment->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        // Optionally delete attached image
        if ($comment->image_url) {
            Storage::disk('s3')->delete($comment->image_url);
        }

        $comment->delete();

        return back()->with('success', 'Comment deleted successfully.');
    }

    
}
