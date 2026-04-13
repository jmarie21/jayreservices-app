<?php

namespace App\Http\Controllers;

use App\Mail\EditorProjectCommentMail;
use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\ProjectCommentAttachment;
use App\Models\User;
use App\Notifications\ClientCommentNotification;
use App\Notifications\ClientProjectCommentNotification;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use League\Flysystem\UnableToWriteFile;

class CommentController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate($this->commentRules());
        $uploadedAttachments = $this->uploadedAttachments($request);

        $this->ensureCommentHasContent(
            body: $validated['body'] ?? null,
            attachmentCount: count($uploadedAttachments),
        );

        $comment = $project->comments()->create([
            'user_id' => auth()->id(),
            'body' => $validated['body'] ?? null,
            'image_url' => null,
        ]);

        $this->appendAttachmentsToComment($comment, $uploadedAttachments);
        $comment->load(['user', 'attachments']);

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

            // ✅ Notify the client about admin's comment
            if ($project->client_id) {
                $client = User::find($project->client_id);
                if ($client) {
                    $client->notify(new ClientProjectCommentNotification($comment, $project));
                    $this->queueClientCommentEmail($client, $comment, $project);

                    Log::info('Client notified about admin comment', [
                        'project_id' => $project->id,
                        'project_name' => $project->project_name,
                        'comment_id' => $comment->id,
                        'client_id' => $client->id,
                        'client_name' => $client->name,
                        'commenter' => 'admin',
                    ]);
                }
            }
        }

        // ✅ Notify client if comment is from editor
        if ($user->role === 'editor') {
            // Notify the client about editor's comment
            if ($project->client_id) {
                $client = User::find($project->client_id);
                if ($client) {
                    $client->notify(new ClientProjectCommentNotification($comment, $project));
                    $this->queueClientCommentEmail($client, $comment, $project);

                    Log::info('Client notified about editor comment', [
                        'project_id' => $project->id,
                        'project_name' => $project->project_name,
                        'comment_id' => $comment->id,
                        'client_id' => $client->id,
                        'client_name' => $client->name,
                        'commenter' => 'editor',
                    ]);
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

    protected function queueClientCommentEmail(User $client, ProjectComment $comment, Project $project): void
    {
        $recipients = collect($client->getAllEmails())
            ->filter(fn ($email) => is_string($email) && $email !== '')
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->queue(new EditorProjectCommentMail($comment, $project));
    }

    public function update(Request $request, ProjectComment $comment)
    {
        // Only owner or admin can update
        if ($comment->user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate(array_merge($this->commentRules(), [
            'keep_attachment_ids' => ['nullable', 'array'],
            'keep_attachment_ids.*' => ['integer'],
            'keep_legacy_image' => ['nullable', 'boolean'],
        ]));

        $uploadedAttachments = $this->uploadedAttachments($request);
        $keepAttachmentIds = collect($validated['keep_attachment_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $keptAttachments = $comment->attachments()
            ->whereIn('id', $keepAttachmentIds)
            ->orderBy('position')
            ->get();

        $keepLegacyImage = (bool) ($validated['keep_legacy_image'] ?? ! empty($comment->image_url));

        $this->ensureAttachmentLimit(
            $keptAttachments->count() + count($uploadedAttachments) + ($keepLegacyImage && $comment->image_url ? 1 : 0)
        );

        $this->ensureCommentHasContent(
            body: $validated['body'] ?? null,
            attachmentCount: $keptAttachments->count() + count($uploadedAttachments) + ($keepLegacyImage && $comment->image_url ? 1 : 0),
        );

        $attachmentsToDelete = $comment->attachments()
            ->whereNotIn('id', $keepAttachmentIds)
            ->get();

        foreach ($attachmentsToDelete as $attachment) {
            $this->deleteAttachmentRecord($attachment);
        }

        $nextPosition = 0;

        foreach ($keptAttachments as $attachment) {
            if ($attachment->position !== $nextPosition) {
                $attachment->update(['position' => $nextPosition]);
            }

            $nextPosition++;
        }

        if ($comment->image_url && ! $keepLegacyImage) {
            $this->deleteCommentImage($comment->image_url);
            $comment->image_url = null;
        }

        $comment->body = $validated['body'] ?? null;
        $comment->save();

        $this->appendAttachmentsToComment($comment, $uploadedAttachments, $nextPosition);

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

        foreach ($comment->attachments as $attachment) {
            $this->deleteAttachmentRecord($attachment);
        }

        // Optionally delete attached image
        if ($comment->image_url) {
            $this->deleteCommentImage($comment->image_url);
        }

        $comment->delete();

        return back()->with('success', 'Comment deleted successfully.');
    }

    protected function commentRules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,webp,mp4,mov,webm,quicktime',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $mime = $value->getMimeType() ?: '';
                    $sizeBytes = $value->getSize();

                    if (str_starts_with($mime, 'image/') && $sizeBytes > 5 * 1024 * 1024) {
                        $fail('Images must be 5 MB or smaller.');

                        return;
                    }

                    if (str_starts_with($mime, 'video/') && $sizeBytes > 25 * 1024 * 1024) {
                        $fail('Videos must be 25 MB or smaller.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    protected function uploadedAttachments(Request $request): array
    {
        return collect($request->file('attachments', []))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values()
            ->all();
    }

    protected function ensureCommentHasContent(?string $body, int $attachmentCount): void
    {
        if (filled(trim((string) $body)) || $attachmentCount > 0) {
            return;
        }

        throw ValidationException::withMessages([
            'body' => 'Comment cannot be empty.',
        ]);
    }

    protected function ensureAttachmentLimit(int $attachmentCount): void
    {
        if ($attachmentCount <= 3) {
            return;
        }

        throw ValidationException::withMessages([
            'attachments' => 'You may attach up to 3 files per comment.',
        ]);
    }

    protected function appendAttachmentsToComment(ProjectComment $comment, array $uploadedAttachments, int $startingPosition = 0): void
    {
        $position = $startingPosition;

        foreach ($uploadedAttachments as $file) {
            $mimeType = $file->getClientMimeType() ?: $file->getMimeType();
            $originalName = $file->getClientOriginalName();
            $sizeBytes = $file->getSize();
            $storedFile = $this->storeCommentAttachment($file);

            $comment->attachments()->create([
                'disk' => $storedFile['disk'],
                'path' => $storedFile['path'],
                'mime_type' => $mimeType,
                'original_name' => $originalName,
                'size_bytes' => $sizeBytes,
                'position' => $position,
            ]);

            $position++;
        }
    }

    /**
     * @return array{disk:string,path:string}
     */
    protected function storeCommentAttachment(UploadedFile $file): array
    {
        try {
            $path = $file->store('chat-comments', 's3');

            if (! is_string($path) || $path === '') {
                throw new UnableToWriteFile('Unable to store comment attachment on s3.');
            }

            return [
                'disk' => 's3',
                'path' => $path,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Comment attachment upload to s3 failed, falling back to the public web directory.', [
                'message' => $exception->getMessage(),
            ]);

            $directory = public_path('chat-comments');
            File::ensureDirectoryExists($directory);

            $fileName = $file->hashName();
            $file->move($directory, $fileName);

            return [
                'disk' => 'public_path',
                'path' => 'chat-comments/'.$fileName,
            ];
        }
    }

    protected function deleteAttachmentRecord(ProjectCommentAttachment $attachment): void
    {
        $this->deleteStoredFile($attachment->disk, $attachment->path);
        $attachment->delete();
    }

    protected function deleteStoredFile(string $disk, string $path): void
    {
        if ($path === '') {
            return;
        }

        if ($disk === 'public_path') {
            File::delete(public_path($path));

            return;
        }

        Storage::disk($disk)->delete($path);
    }

    protected function deleteCommentImage(?string $storedImage): void
    {
        if (! $storedImage) {
            return;
        }

        [$disk, $path] = $this->resolveCommentImageLocation($storedImage);

        if ($path === '') {
            return;
        }

        if ($disk === 'public_path') {
            File::delete(public_path($path));

            return;
        }

        $this->deleteStoredFile($disk, $path);
    }

    /**
     * @return array{0:string,1:string}
     */
    protected function resolveCommentImageLocation(string $storedImage): array
    {
        $value = trim($storedImage);

        if ($value === '') {
            return ['public', ''];
        }

        if (str_starts_with($value, 's3://')) {
            return ['s3', substr($value, strlen('s3://'))];
        }

        if (str_starts_with($value, 'public://')) {
            return ['public', substr($value, strlen('public://'))];
        }

        if (str_starts_with($value, 'public-path://')) {
            return ['public_path', substr($value, strlen('public-path://'))];
        }

        if (str_starts_with($value, '/storage/')) {
            return ['public', substr($value, strlen('/storage/'))];
        }

        if (str_starts_with($value, 'storage/')) {
            return ['public', substr($value, strlen('storage/'))];
        }

        if (str_starts_with($value, '/chat-comments/')) {
            return ['public_path', ltrim($value, '/')];
        }

        if (str_starts_with($value, 'chat-comments/')) {
            return ['public_path', $value];
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = ltrim((string) parse_url($value, PHP_URL_PATH), '/');

            if (str_starts_with($path, 'storage/')) {
                return ['public', substr($path, strlen('storage/'))];
            }

            if (str_starts_with($path, 'chat-comments/')) {
                return ['public_path', $path];
            }

            return ['s3', $path];
        }

        return ['s3', $value];
    }
}
