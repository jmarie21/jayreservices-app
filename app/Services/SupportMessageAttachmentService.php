<?php

namespace App\Services;

use App\Models\SupportMessage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use League\Flysystem\UnableToWriteFile;

class SupportMessageAttachmentService
{
    public function storeAttachments(Request $request, SupportMessage $message): void
    {
        $files = collect($request->file('attachments', []))
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        if ($files->isEmpty()) {
            return;
        }

        $position = 0;

        foreach ($files as $file) {
            $stored = $this->storeFile($file);

            $message->attachments()->create([
                'disk' => $stored['disk'],
                'path' => $stored['path'],
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'original_name' => $file->getClientOriginalName(),
                'size_bytes' => $file->getSize(),
                'position' => $position,
            ]);

            $position++;
        }
    }

    /**
     * @return array{disk:string,path:string}
     */
    protected function storeFile(UploadedFile $file): array
    {
        try {
            $path = $file->store('chat-comments', 's3');

            if (! is_string($path) || $path === '') {
                throw new UnableToWriteFile('Unable to store chat attachment on s3.');
            }

            return [
                'disk' => 's3',
                'path' => $path,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Chat attachment upload to s3 failed, falling back to the public web directory.', [
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
}
