<?php

namespace App\Console\Commands;

use App\Domain\Website\PictureLibrary;
use App\Models\MediaAsset;
use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateMediaToR2 extends Command
{
    protected $signature = 'media:migrate-to-r2 {--dry-run : List what would be copied without writing objects or database metadata}';

    protected $description = 'Copy available legacy local pictures to R2 without deleting source files or changing media IDs/paths';

    public function handle(PictureLibrary $pictures): int
    {
        if (config('filesystems.disks.public.driver') !== 's3'
            || config('filesystems.disks.student_photos.driver') !== 's3'
            || ! filled(config('filesystems.disks.public.bucket'))
            || ! filled(config('filesystems.disks.public.key'))
            || ! filled(config('filesystems.disks.public.secret'))
            || ! filled(config('filesystems.disks.public.endpoint'))) {
            $this->error('Configure the R2 environment and set PUBLIC_FILESYSTEM_DRIVER=s3 before running this command.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $legacy = Storage::disk('legacy_public');
        $public = Storage::disk('public');
        $private = Storage::disk('student_photos');
        $studentAssetIds = Student::query()->whereNotNull('photo_id')->pluck('photo_id')->map(fn ($id) => (int) $id)->unique();
        $copiedPublic = 0;
        $copiedPrivate = 0;
        $missing = [];
        $skipped = 0;

        foreach ($studentAssetIds as $assetId) {
            $asset = MediaAsset::query()->find($assetId);
            if (! $asset || ! str_starts_with($asset->path, 'student-passports/') || str_contains($asset->path, '..')) {
                continue;
            }
            if ($asset->disk === 'student_photos' && $private->exists($asset->path)) {
                $skipped++;
                continue;
            }
            $sourceDisk = $legacy->exists($asset->path) ? $legacy : ($public->exists($asset->path) ? $public : null);
            if (! $sourceDisk) {
                $missing[] = $asset->path;
                continue;
            }
            if ($dryRun) {
                $this->line('Would move student photo to private R2 prefix: '.$asset->path);
                continue;
            }
            $this->copy($sourceDisk, $private, $asset->path);
            $asset->forceFill(['disk' => 'student_photos'])->save();
            $copiedPrivate++;
        }

        foreach ($pictures->references() as $reference) {
            $path = $reference['path'];
            if (str_starts_with($path, 'student-passports/')) {
                continue;
            }
            if (! $legacy->exists($path)) {
                if (! $public->exists($path)) {
                    $missing[] = $path;
                } else {
                    $skipped++;
                }
                continue;
            }
            if ($public->exists($path)) {
                $skipped++;
                continue;
            }
            if ($dryRun) {
                $this->line('Would copy public media to R2: '.$path);
                continue;
            }
            $this->copy($legacy, $public, $path);
            $copiedPublic++;
        }

        $this->info(($dryRun ? 'Dry run complete. ' : 'Migration complete. ')."Public copied: {$copiedPublic}; private student photos copied: {$copiedPrivate}; already present/skipped: {$skipped}; missing: ".count(array_unique($missing)).'.');
        if ($missing !== []) {
            $this->warn('Missing source files (not deleted or detached):');
            foreach (array_unique($missing) as $path) {
                $this->line(' - '.$path);
            }
        }
        $this->line('No source files or database records were deleted.');

        return self::SUCCESS;
    }

    private function copy($source, $destination, string $path): void
    {
        $stream = $source->readStream($path);
        if (! is_resource($stream)) {
            throw new \RuntimeException('Cannot open media source: '.$path);
        }
        try {
            if (! $destination->writeStream($path, $stream)) {
                throw new \RuntimeException('Cannot write media destination: '.$path);
            }
        } finally {
            fclose($stream);
        }
    }
}
