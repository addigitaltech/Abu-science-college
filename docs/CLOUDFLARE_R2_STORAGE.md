# Cloudflare R2 storage for Abu Science College

## Why storage is changing

The app previously wrote uploads to Laravel's `public` disk at `storage/app/public`. Render's service filesystem is not durable, so an upload can disappear on a restart, redeploy, or instance replacement even though its `media_assets` database row and a student's `photo_id` remain. That mismatch is why a photo can appear to have uploaded successfully and later return 404.

The database remains the source of media metadata. This change keeps existing media IDs and stored paths, while moving the bytes to an S3-compatible R2 object store. R2 access keys are runtime environment values and are never stored in this repository.

## Render environment variables

Create one Cloudflare R2 bucket and leave it private (do not enable public bucket access or add a public bucket domain). Create an R2 API token scoped to that bucket with Object Read & Write permission. Configure these exact variables in the Render web service's Environment settings:

```dotenv
FILESYSTEM_DISK=public
PUBLIC_FILESYSTEM_DRIVER=s3
STUDENT_PHOTOS_DRIVER=s3
PUBLIC_FILESYSTEM_VISIBILITY=private
R2_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
R2_BUCKET=<your-r2-bucket-name>
R2_ACCESS_KEY_ID=<r2-access-key-id>
R2_SECRET_ACCESS_KEY=<r2-secret-access-key>
R2_REGION=auto
R2_USE_PATH_STYLE_ENDPOINT=false
R2_PUBLIC_PREFIX=
R2_PRIVATE_PREFIX=private
```

Replace the angle-bracket examples with your account's actual values; do not include the brackets. The endpoint is the account-level S3 API endpoint, not a public bucket URL. Public school assets (logos, gallery images, news/programme/event images, and media-library documents) are stored under the public-disk prefix and served by the existing application `/media/...` route. Student portraits are written beneath the private `private/` object prefix and are served only after app authorization or through a one-minute signed image URL attached to an already-authorized public result-check report. The bucket itself remains private. R2 does not implement S3 object ACL headers; the Laravel S3 upload callback removes those headers, and privacy is enforced by the private bucket plus the application routes.

After saving the variables, redeploy the service so the runtime uses the new configuration. Do not copy the R2 secret into GitHub, `.env.example`, logs, or chat. Rotate the R2 token if it is exposed.

## Existing files and safe migration

The command `php artisan media:migrate-to-r2 --dry-run` lists files that can still be read from the old local `storage/app/public` directory and any already-present R2 objects. Run `php artisan media:migrate-to-r2` only after the R2 variables above are configured. The command copies referenced legacy public media to R2 and copies linked student passports into the private prefix. It keeps source files, does not delete media records, and retains each media ID and path. For a student portrait, only the existing `media_assets.disk` value changes to `student_photos` after the private copy succeeds.

If a file is absent from both the old local directory and R2, the command reports it and leaves the database link intact. The current Render/Neon diagnosis found 24 non-deleted student-passport records (about 4.38 MB of metadata-referenced photos) whose image bytes were already missing from Render's local disk. Those files cannot be reconstructed from the database; the school will need to upload them again unless they are recovered from a separate backup. The migration command can copy only files it can actually read.

If older media is still present on a running instance, copy it before the instance is replaced. Render does not provide a supported way for a new deployment to recover files from an already-discarded ephemeral instance. Never run broad deletion/cleanup commands as part of this migration.

## Hosting behavior

Render uses the `s3` drivers above. cPanel remains on the existing local filesystem by default: leave `PUBLIC_FILESYSTEM_DRIVER=local` and `STUDENT_PHOTOS_DRIVER=local` (or omit them). Public uploads continue to work through `/storage` or `/media`, and private student photos are stored outside `public/` and retrieved through the authenticated media route. Back up the full `storage/app` directory on cPanel, along with the database.

Neon remains the database and GitHub remains the code repository. No database engine, records, school features, or UI layout are replaced by this change.

## Verification and remaining live checks

Focused automated tests cover local media serving, private signed-URL access and tamper rejection, storage-agnostic image data-URI preparation, and ensuring backup archives restore student portraits to the private disk. They do not substitute for a live R2 request or a visual PDF-render check. A live R2 upload/retrieval check requires a real configured R2 bucket and credentials. Persistence across Render restart/redeploy can only be verified after those variables are configured and a test upload is confirmed in the bucket both before and after the deployment event. Do not use Neon production data for destructive tests.

## Official references

- [Cloudflare R2 S3 API compatibility and endpoint](https://developers.cloudflare.com/r2/get-started/s3/)
- [Cloudflare R2 S3 API operation support (including unsupported ACL parameters)](https://developers.cloudflare.com/r2/api/s3/api/)
- [Cloudflare R2 presigned URLs](https://developers.cloudflare.com/r2/api/s3/presigned-urls/)
- [Laravel 12 filesystem configuration and S3 disks](https://laravel.com/docs/12.x/filesystem)
