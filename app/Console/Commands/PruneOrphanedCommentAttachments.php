<?php

namespace App\Console\Commands;

use App\Models\ProjectComment;
use App\Models\ProjectCommentAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneOrphanedCommentAttachments extends Command
{
    protected $signature = 'attachments:prune-orphans';

    protected $description = 'Delete S3 comment-attachment files that are no longer referenced by any database row';

    public function handle(): int
    {
        $disk = Storage::disk('s3');
        $prefix = 'chat-comments';

        $allFiles = $disk->files($prefix);

        if (count($allFiles) === 0) {
            $this->info('No files found in S3 under "'.$prefix.'/".');

            return self::SUCCESS;
        }

        $referencedPaths = ProjectCommentAttachment::query()
            ->where('disk', 's3')
            ->pluck('path')
            ->toArray();

        $legacyPaths = ProjectComment::query()
            ->whereNotNull('image_url')
            ->where('image_url', '!=', '')
            ->pluck('image_url')
            ->filter(fn (string $url) => str_contains($url, $prefix))
            ->map(function (string $url) use ($prefix) {
                if (str_starts_with($url, 's3://')) {
                    return substr($url, strlen('s3://'));
                }

                $path = parse_url($url, PHP_URL_PATH);

                if ($path && str_contains($path, $prefix)) {
                    return ltrim(substr($path, (int) strpos($path, $prefix)), '/');
                }

                return $url;
            })
            ->toArray();

        $referenced = array_flip(array_merge($referencedPaths, $legacyPaths));
        $gracePeriod = Carbon::now()->subHours(24);
        $deleted = 0;

        foreach ($allFiles as $file) {
            if (isset($referenced[$file])) {
                continue;
            }

            $lastModified = $disk->lastModified($file);

            if (Carbon::createFromTimestamp($lastModified)->isAfter($gracePeriod)) {
                continue;
            }

            $disk->delete($file);
            $deleted++;
        }

        $message = "Pruned {$deleted} orphaned comment attachment(s) from S3.";
        $this->info($message);
        Log::info($message);

        return self::SUCCESS;
    }
}
