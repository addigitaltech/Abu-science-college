<?php

namespace App\Http\Middleware;

use App\Support\MediaUrl;
use Closure;
use Illuminate\Http\Request;

/** Makes storage previews use the active host and the same safe media-serving route. */
class ConfigureMediaUrl
{
    public function handle(Request $request, Closure $next)
    {
        config(['filesystems.disks.public.url' => MediaUrl::base()]);

        return $next($request);
    }
}
