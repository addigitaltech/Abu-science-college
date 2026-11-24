<?php

namespace App\Http\Controllers;

use App\Domain\Results\Services\ResultSheetService;
use App\Models\MediaAsset;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves public media and separately authorized student portraits. */
class MediaController extends Controller
{
    private const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon', 'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
    ];

    public function show(string $path): BinaryFileResponse|StreamedResponse
    {
        $path = trim($path, '/');
        abort_if($path === '' || strlen($path) > 300 || preg_match('#(^|/)\.|\\\\|\0|//#', $path) || str_contains($path, '..'), 404);
        abort_if(str_starts_with($path, 'student-passports/'), 404);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        abort_unless(isset(self::TYPES[$extension]), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path) ?: self::TYPES[$extension];
        abort_unless($mime === self::TYPES[$extension] || ($extension === 'svg' && $mime === 'image/svg+xml'), 404);
        $headers = $this->headers($mime, 'public, max-age=86400', $extension === 'svg');

        if (config('filesystems.disks.public.driver') === 'local') {
            $full = $disk->path($path);
            $root = realpath((string) config('filesystems.disks.public.root'));
            $real = realpath($full);
            abort_unless($root && $real && str_starts_with($real, $root.DIRECTORY_SEPARATOR) && is_file($real), 404);

            return response()->file($real, $headers);
        }

        return $this->stream($disk->readStream($path), $headers);
    }

    public function studentPhoto(MediaAsset $mediaAsset, Request $request, ResultSheetService $sheets): StreamedResponse
    {
        $this->assertStudentPhoto($mediaAsset);
        abort_unless($this->mayViewStudentPhoto($request->user(), $mediaAsset, $sheets), 403);

        return $this->privatePhotoResponse($mediaAsset);
    }

    /** A short-lived signed capability used only by the public, signed result-check report. */
    public function sharedStudentPhoto(MediaAsset $mediaAsset): StreamedResponse
    {
        $this->assertStudentPhoto($mediaAsset);

        return $this->privatePhotoResponse($mediaAsset);
    }

    private function assertStudentPhoto(MediaAsset $asset): void
    {
        abort_unless($asset->disk === 'student_photos'
            && str_starts_with($asset->path, 'student-passports/')
            && ! str_contains($asset->path, '..')
            && in_array(strtolower(pathinfo($asset->path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true), 404);
    }

    private function mayViewStudentPhoto(?\App\Models\User $user, MediaAsset $asset, ResultSheetService $sheets): bool
    {
        if (! $user) {
            return false;
        }
        $student = Student::query()->where('photo_id', $asset->getKey())->first();
        if (! $student) {
            return false;
        }
        if ($user->hasRole('Super Admin') || $user->can('manage students') || $user->can('manage results')) {
            return true;
        }
        if ($user->hasRole('Student')) {
            return (int) $user->studentProfile()->value('id') === (int) $student->id;
        }
        if ($user->hasRole('Parent')) {
            return (bool) $user->parentProfile()->first()?->students()->whereKey($student->id)->exists();
        }

        return $user->hasRole('Teacher') && $sheets->teachesClass($user, $student->school_class_id, $student->arm_id);
    }

    private function privatePhotoResponse(MediaAsset $asset): StreamedResponse
    {
        $disk = Storage::disk($asset->disk);
        abort_unless($disk->exists($asset->path), 404);
        $mime = $disk->mimeType($asset->path) ?: $asset->mime_type;
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return $this->stream($disk->readStream($asset->path), $this->headers($mime, 'private, no-store', false));
    }

    /** @param resource|false $stream */
    private function stream($stream, array $headers): StreamedResponse
    {
        abort_unless(is_resource($stream), 404);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 200, $headers);
    }

    private function headers(string $mime, string $cache, bool $svg): array
    {
        $headers = ['Content-Type' => $mime, 'Cache-Control' => $cache, 'X-Content-Type-Options' => 'nosniff'];
        if ($svg) {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; sandbox";
        }

        return $headers;
    }
}
