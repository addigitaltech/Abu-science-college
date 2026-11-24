<?php

namespace Tests\Feature;

use App\Domain\Website\PictureLibrary;
use App\Filament\Pages\SecurityCheck;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\MediaContent;
use App\Support\MediaUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

/** Pictures show on any host (XAMPP included) and the website turns away probing and unsafe uploads. */
class MediaAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        File::ensureDirectoryExists(storage_path('app/public/test-media'));
        file_put_contents(storage_path('app/public/test-media/pixel.png'), base64_decode(self::PNG));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/public/test-media'));
        parent::tearDown();
    }

    #[Test]
    public function pictures_are_served_through_the_media_route_without_the_storage_link(): void
    {
        $response = $this->get('/media/test-media/pixel.png')->assertOk();

        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('test-media/pixel.png', (string) MediaUrl::for('test-media/pixel.png'));
    }

    #[Test]
    public function the_media_route_refuses_traversal_hidden_files_scripts_and_unknown_files(): void
    {
        $this->assertContains($this->get('/media/../../.env')->status(), [400, 404]);
        $this->assertContains($this->get('/media/test-media/%2e%2e/%2e%2e/.env')->status(), [400, 404]);
        $this->get('/media/test-media/.hidden.png')->assertNotFound();
        $this->get('/media/test-media/shell.php')->assertNotFound();
        $this->get('/media/test-media/missing.png')->assertNotFound();
    }

    #[Test]
    public function a_picture_address_is_absolute_and_safe_to_put_in_a_page(): void
    {
        $url = MediaUrl::for('student-passports/ada okoro.jpg');

        $this->assertStringStartsWith('http', $url);
        $this->assertStringContainsString('ada%20okoro.jpg', $url);
        $this->assertNull(MediaUrl::for(''));
        $this->assertSame('https://cdn.example/x.jpg', MediaUrl::for('https://cdn.example/x.jpg'));
    }

    #[Test]
    public function a_media_asset_uses_the_safe_address(): void
    {
        $asset = MediaAsset::query()->create(['original_name' => 'pixel.png', 'path' => 'test-media/pixel.png', 'disk' => 'public', 'mime_type' => 'image/png', 'size_bytes' => 70]);

        $this->assertStringContainsString('test-media/pixel.png', $asset->url());
        $this->assertStringStartsWith('http', $asset->url());
    }

    #[Test]
    public function private_student_portraits_require_a_valid_short_lived_signed_url(): void
    {
        Storage::fake('student_photos');
        Storage::disk('student_photos')->put('student-passports/private.png', base64_decode(self::PNG));
        $asset = MediaAsset::query()->create(['original_name' => 'private.png', 'path' => 'student-passports/private.png', 'disk' => 'student_photos', 'mime_type' => 'image/png', 'size_bytes' => 70]);

        $this->get(route('media.student-photo.shared', ['mediaAsset' => $asset->id]))->assertForbidden();
        $url = URL::temporarySignedRoute('media.student-photo.shared', now()->addMinute(), ['mediaAsset' => $asset->id]);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get($url.'&tampered=1')->assertForbidden();
    }

    #[Test]
    public function stored_images_can_be_embedded_in_pdfs_without_a_local_filesystem_path(): void
    {
        Storage::fake('student_photos');
        Storage::disk('student_photos')->put('student-passports/pdf.png', base64_decode(self::PNG));

        $dataUri = MediaContent::dataUri('student_photos', 'student-passports/pdf.png', 'image/png');

        $this->assertNotNull($dataUri);
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    #[Test]
    public function the_r2_upload_callback_removes_unsupported_object_acl_parameters(): void
    {
        $command = new \Aws\Command('PutObject', ['ACL' => 'private', 'Bucket' => 'private-bucket']);

        \App\Support\R2ObjectAcl::remove($command);

        $this->assertFalse($command->hasParam('ACL'));
        $this->assertSame('private-bucket', $command['Bucket']);
    }

    #[Test]
    public function the_picture_check_lists_pictures_the_database_points_to_that_are_not_on_disk(): void
    {
        MediaAsset::query()->create(['original_name' => 'gone.jpg', 'path' => 'student-passports/gone.jpg', 'disk' => 'public', 'mime_type' => 'image/jpeg', 'size_bytes' => 10]);
        MediaAsset::query()->create(['original_name' => 'pixel.png', 'path' => 'test-media/pixel.png', 'disk' => 'public', 'mime_type' => 'image/png', 'size_bytes' => 70]);

        $missing = app(PictureLibrary::class)->missing();

        $this->assertSame(['student-passports/gone.jpg'], $missing->pluck('path')->all());
    }

    #[Test]
    public function the_picture_zip_contains_the_pictures_and_a_manifest(): void
    {
        Storage::fake('student_photos');
        Storage::disk('student_photos')->put('student-passports/private.png', base64_decode(self::PNG));
        $tmp = app(PictureLibrary::class)->zip();
        $zip = new ZipArchive();
        $zip->open($tmp);

        $this->assertNotFalse($zip->locateName('test-media/pixel.png'));
        $this->assertNotFalse($zip->locateName('private-student-photos/student-passports/private.png'));
        $this->assertNotFalse($zip->locateName('manifest.csv'));
        $zip->close();
        @unlink($tmp);
    }

    #[Test]
    public function loading_a_picture_zip_keeps_safe_pictures_and_refuses_scripts_traversal_and_fake_pictures(): void
    {
        Storage::fake('public');
        Storage::fake('student_photos');
        $zipPath = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString('gallery/ok.png', base64_decode(self::PNG));
        $zip->addFromString('gallery/shell.php', '<?php echo 1;');
        $zip->addFromString('../escape.png', base64_decode(self::PNG));
        $zip->addFromString('gallery/.hidden.png', base64_decode(self::PNG));
        $zip->addFromString('gallery/fake.png', '<?php system($_GET["c"]); ?>');
        $zip->addFromString('student-passports/private.png', base64_decode(self::PNG));
        $zip->close();

        $result = app(PictureLibrary::class)->import($zipPath);

        $this->assertSame(2, $result['added']);
        $this->assertCount(4, $result['rejected']);
        Storage::disk('public')->assertExists('gallery/ok.png');
        Storage::disk('student_photos')->assertExists('student-passports/private.png');
        Storage::disk('public')->assertMissing('student-passports/private.png');
        Storage::disk('public')->assertMissing('gallery/shell.php');
        Storage::disk('public')->assertMissing('gallery/fake.png');

        // Loading the same ZIP again never overwrites what is there.
        $again = app(PictureLibrary::class)->import($zipPath);
        $this->assertSame(0, $again['added']);
        $this->assertSame(2, $again['skipped']);
        @unlink($zipPath);
    }

    #[Test]
    public function requests_that_only_bots_make_are_turned_away(): void
    {
        foreach (['/wp-login.php', '/wp-admin/install.php', '/.env', '/.git/config', '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php', '/phpmyadmin/index.php', '/composer.json', '/shell.php', '/backup.sql'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->get('/')->assertOk();
        $this->get('/'.str_repeat('a', 3000))->assertStatus(414);
    }

    #[Test]
    public function the_contact_form_asks_for_the_robot_check_when_turnstile_is_on_and_still_has_its_honeypot(): void
    {
        config(['services.turnstile.site_key' => 'site-key', 'services.turnstile.secret_key' => 'secret-key']);

        $this->get(route('contact'))->assertOk()->assertSee('data-action="contact"', false);
        $this->post(route('contact.send'), ['name' => 'Bot', 'email' => 'bot@example.test', 'message' => 'Buy cheap things now please', 'website' => 'http://spam.example'])
            ->assertSessionHasErrors();
        $this->post(route('contact.send'), ['name' => 'Ada', 'email' => 'ada@example.test', 'message' => 'A genuine message for the school office.'])
            ->assertSessionHasErrors('cf-turnstile-response');
    }

    #[Test]
    public function only_the_super_admin_sees_the_security_check(): void
    {
        $results = User::factory()->create();
        $results->assignRole('Results Admin');
        $admin = User::factory()->create();
        $admin->assignRole('Super Admin');

        $this->actingAs($results);
        $this->assertFalse(SecurityCheck::canAccess());

        $this->actingAs($admin)->get('/admin/security-check')->assertOk()->assertSee('Security check')->assertSee('Debug mode is off');
    }

    #[Test]
    public function uploaded_scripts_cannot_run_from_the_picture_folder_and_hidden_files_are_blocked(): void
    {
        $this->assertStringContainsString('Require all denied', (string) file_get_contents(base_path('storage/app/public/.htaccess')));
        $this->assertStringContainsString('FilesMatch', (string) file_get_contents(base_path('public/.htaccess')));
        $this->assertStringContainsString('RewriteRule ^(?!public/)', (string) file_get_contents(base_path('.htaccess')));
    }

    #[Test]
    public function login_cookies_are_secure_by_default_in_production(): void
    {
        $config = (string) file_get_contents(config_path('session.php'));

        $this->assertStringContainsString("env('APP_ENV') === 'production'", $config);
    }
}
