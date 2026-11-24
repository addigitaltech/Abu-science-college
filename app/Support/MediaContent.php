<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Reads a stored image from any configured Laravel disk for embedding in generated PDFs. */
final class MediaContent
{
    public static function dataUri(?string $diskName, ?string $path, ?string $fallbackMime = null): ?string
    {
        if (! $diskName || ! $path || preg_match('#^https?://#i', $path)) {
            return null;
        }

        try {
            $disk = Storage::disk($diskName);
            if (! $disk->exists($path)) {
                return null;
            }
            $mime = $disk->mimeType($path) ?: $fallbackMime;
            if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/x-icon', 'image/vnd.microsoft.icon'], true)) {
                return null;
            }
            $content = $disk->get($path);
            if ($content === '') {
                return null;
            }

            return 'data:'.$mime.';base64,'.base64_encode($content);
        } catch (\Throwable) {
            return null;
        }
    }
}
