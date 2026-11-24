<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Resolves public school media URLs on local hosts and R2-backed Render deployments. */
final class MediaUrl
{
    public static function base(): string
    {
        if (config('filesystems.disks.public.driver') === 's3') {
            return rtrim(url('/media'), '/');
        }

        return is_dir(public_path('storage')) ? rtrim(asset('storage'), '/') : rtrim(url('media'), '/');
    }

    public static function for(?string $path): ?string
    {
        $path = trim((string) $path, '/');
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return self::base().'/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}
