<?php

namespace Database\Seeders;

use App\Models\AcademicProgramme;
use App\Models\AdmissionsSettings;
use App\Models\CmsPage;
use App\Models\FooterSection;
use App\Models\GalleryAlbum;
use App\Models\GalleryImage;
use App\Models\HeroSlide;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\PaymentGatewayConfig;
use App\Models\PortalLink;
use App\Models\SchoolSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class PublicContentSeeder extends Seeder
{
    public function run(): void
    {
        $imageMeta = [
            'logo.jpg' => ['Official Abu Science College crest', 'Abu Science College crest'],
            'school-gate.jpg' => ['Abu Science College campus building', 'Campus building'],
            'campus-building-2.jpg' => ['Abu Science College campus building', 'School campus'],
            'students-group.jpg' => ['Students of Abu Science College in school uniform', 'Students'],
            'students-reading.jpg' => ['Abu Science College students with their books', 'Students and books'],
            'textbooks.jpg' => ['Abu Science College students with their books', 'Students and books'],
        ];
        $media = [];
        foreach ($imageMeta as $filename => [$alt, $caption]) {
            $path = 'migrated-images/'.$filename;
            $media[$filename] = MediaAsset::query()->updateOrCreate(
                ['path' => $path],
                [
                    'uploaded_by' => null,
                    'original_name' => $filename,
                    'disk' => 'public',
                    'mime_type' => 'image/jpeg',
                    'size_bytes' => (int) (Storage::disk('public')->size($path) ?: 0),
                    'alt_text' => $alt,
                    'caption' => $caption,
                ],
            );
        }

        SchoolSettings::query()->updateOrCreate(['id' => 1], [
            'school_name' => 'Abu Science College',
            'short_name' => 'Abu Science College',
            'motto' => 'READERS ARE LEADERS',
            'description' => 'Abu Science College is a secondary school in Ugbe Akoko, Ondo State, Nigeria. The school is committed to quality education, academic excellence, scientific understanding and technological advancement, nurturing disciplined, confident and responsible learners.',
            'logo_path' => 'migrated-images/logo.jpg',
            'favicon_path' => 'migrated-images/logo.jpg',
            'address' => 'Ugbe Akoko, Ondo State, Nigeria',
            'phone_primary' => '+234 815 533 2069',
            'phone_secondary' => '0903 598 9153',
            'email' => null,
            'whatsapp' => null,
            'office_hours' => null,
            'social_links' => [],
            'theme_tokens' => [
                'primary' => '#163b9c', 'secondary' => '#102f80', 'brand_50' => '#eef2ff', 'brand_100' => '#dbe4ff', 'brand_200' => '#b8c9ff',
                'brand_400' => '#5474d4', 'brand_600' => '#163b9c', 'brand_700' => '#102f80',
                'brand_800' => '#0b2465', 'brand_900' => '#071941', 'accent' => '#d6a631',
                'ink' => '#15213b', 'surface_alt' => '#f7f8fc', 'surface_dark' => '#071941', 'border' => '#dce2ee',
            ],
            'verified_stats' => [],
            'ca1_max_score' => 40,
            'ca2_max_score' => 0,
            'ca3_max_score' => 0,
            'exam_max_score' => 60,
            'pass_percentage' => 40,
            'highlight_fail_grade' => true,
            'payment_gate_results' => false,
            'result_access_mode' => 'portal_only',
        ]);

        $slides = [
            ['eyebrow' => 'ABU SCIENCE COLLEGE · UGBE AKOKO', 'heading' => 'Readers Are Leaders', 'body' => 'Building future leaders through knowledge, science and technology. A secondary school community committed to academic excellence, curiosity and responsible character.', 'image' => 'school-gate.jpg', 'alt' => 'Abu Science College campus building', 'cta_label' => 'Discover Our School', 'cta_url' => '/about', 'secondary_cta_label' => 'Admissions', 'secondary_cta_url' => '/admissions'],
            ['eyebrow' => 'JUNIOR & SENIOR SECONDARY', 'heading' => 'Learn with curiosity. Lead with knowledge.', 'body' => 'Our academic programme serves JSS1–JSS3 and SS1–SS3, with a focus on strong learning foundations and preparation for further study.', 'image' => 'students-group.jpg', 'alt' => 'Abu Science College students in school uniform', 'cta_label' => 'Explore Academics', 'cta_url' => '/academics'],
            ['eyebrow' => 'EARLY JAMB FAMILIARISATION', 'heading' => 'SS1 today. JAMB ready already!', 'body' => 'Early exposure to the JAMB examination environment helps students build confidence and become familiar with computer-based testing.', 'image' => 'textbooks.jpg', 'alt' => 'Abu Science College students holding books', 'cta_label' => 'Meet Our JAMB Achievers', 'cta_url' => '/jamb-achievers'],
        ];
        foreach ($slides as $index => $slide) {
            HeroSlide::query()->updateOrCreate(['heading' => $slide['heading']], [
                'eyebrow' => $slide['eyebrow'], 'body' => $slide['body'], 'image_id' => $media[$slide['image']]->id,
                'image_path' => $media[$slide['image']]->path, 'image_alt' => $slide['alt'],
                'cta_label' => $slide['cta_label'], 'cta_url' => $slide['cta_url'],
                'secondary_cta_label' => $slide['secondary_cta_label'] ?? null,
                'secondary_cta_url' => $slide['secondary_cta_url'] ?? null,
                'is_enabled' => true, 'sort_order' => $index,
            ]);
        }

        $programmes = [
            ['level' => 'junior secondary', 'title' => 'Junior Secondary School', 'slug' => 'junior-secondary', 'intro' => 'The junior secondary programme comprises JSS1, JSS2 and JSS3. Abu Science College encourages academic excellence, scientific curiosity, reading, critical thinking and good character.', 'approach' => ['JSS1', 'JSS2', 'JSS3'], 'placeholder_note' => 'Detailed curriculum and subject combinations should be confirmed by the school administration.', 'image_id' => $media['students-group.jpg']->id, 'image_path' => $media['students-group.jpg']->path],
            ['level' => 'senior secondary', 'title' => 'Senior Secondary School', 'slug' => 'senior-secondary', 'intro' => 'The senior secondary programme comprises SS1, SS2 and SS3. Learning is oriented toward academic achievement, scientific and technological understanding, and preparation for higher education.', 'approach' => ['SS1', 'SS2', 'SS3'], 'placeholder_note' => 'Detailed curriculum, subject combinations and examination information should be confirmed by the school administration.', 'image_id' => $media['textbooks.jpg']->id, 'image_path' => $media['textbooks.jpg']->path],
        ];
        foreach ($programmes as $index => $programme) {
            $programme['is_published'] = true;
            $programme['sort_order'] = $index;
            AcademicProgramme::query()->updateOrCreate(['slug' => $programme['slug']], $programme);
        }

        AdmissionsSettings::query()->updateOrCreate([], [
            'status' => 'open',
            'intro' => 'Admissions are open for the 2026/2027 academic session. Opportunities are listed for JSS1 and SS1. Please contact the school to confirm current availability and the application process.',
            'eligibility' => 'Admissions opportunities listed for JSS1 and SS1. Please contact the school to confirm current availability.',
            'process_steps' => [],
            'requirements' => [],
            'important_dates' => [],
            'screening_information' => 'Admission requirements, procedures and fees are to be confirmed by the school administration.',
            'cta_label' => 'Contact the School',
            'cta_url' => '/contact',
        ]);

        $albums = ['campus' => 'Campus', 'academics' => 'Learning', 'school-life' => 'School Life'];
        foreach ($albums as $slug => $title) {
            $album = GalleryAlbum::query()->updateOrCreate(['slug' => $slug], ['title' => $title, 'status' => 'published']);
            $images = match ($slug) {
                'campus' => [['school-gate.jpg', 'Abu Science College campus building'], ['campus-building-2.jpg', 'A view of the Abu Science College campus']],
                'academics' => [['textbooks.jpg', 'Students of Abu Science College holding books'], ['students-reading.jpg', 'Students of Abu Science College with their books']],
                default => [['students-group.jpg', 'Students of Abu Science College in school uniform']],
            };
            foreach ($images as $index => [$filename, $alt]) {
                GalleryImage::query()->updateOrCreate(
                    ['album_id' => $album->id, 'image_path' => $media[$filename]->path],
                    ['media_id' => $media[$filename]->id, 'caption' => $alt, 'alt_text' => $alt, 'sort_order' => $index, 'is_visible' => true],
                );
            }
        }

        $main = [
            ['Home', '/', null], ['About Us', '/about', null], ['Our Philosophy', '/mission-vision', 'About Us'],
            ['Our History', '/history', 'About Us'], ['JAMB Achievers', '/jamb-achievers', 'About Us'],
            ['Academics', '/academics', null], ['Junior Secondary', '/academics/junior-secondary', 'Academics'],
            ['Senior Secondary', '/academics/senior-secondary', 'Academics'], ['Admissions', '/admissions', null],
            ['News & Announcements', '/news', null], ['Events', '/events', 'News & Announcements'],
            ['Announcements', '/announcements', 'News & Announcements'], ['Gallery', '/gallery', null], ['Contact Us', '/contact', null],
        ];
        $parents = [];
        foreach ($main as $index => [$label, $url, $parentLabel]) {
            $parentId = $parentLabel ? ($parents[$parentLabel] ?? null) : null;
            $item = NavigationItem::query()->updateOrCreate(
                ['menu' => 'main', 'url' => $url, 'parent_id' => $parentId],
                ['label' => $label, 'is_visible' => true, 'sort_order' => $index],
            );
            if (! $parentLabel) $parents[$label] = $item->id;
        }

        foreach ([
            ['Explore', [['label' => 'About Us', 'url' => '/about'], ['label' => 'Our Philosophy', 'url' => '/mission-vision'], ['label' => 'JAMB Achievers', 'url' => '/jamb-achievers'], ['label' => 'Academics', 'url' => '/academics'], ['label' => 'Gallery', 'url' => '/gallery']]],
            ['Admissions', [['label' => 'Admissions Overview', 'url' => '/admissions'], ['label' => 'News & Announcements', 'url' => '/news'], ['label' => 'Events', 'url' => '/events'], ['label' => 'Contact Us', 'url' => '/contact']]],
            ['School Portal', [['label' => 'Student Portal', 'url' => '/student/login'], ['label' => 'All Portals', 'url' => '/portal']]],
        ] as $index => [$title, $links]) {
            FooterSection::query()->updateOrCreate(['title' => $title], ['links' => $links, 'is_visible' => true, 'sort_order' => $index]);
        }

        foreach ([
            ['key' => 'students', 'title' => 'Students', 'description' => 'Sign in to view personal academic records and published results.', 'url' => '/student/login', 'sort_order' => 1],
            ['key' => 'parents', 'title' => 'Parents', 'description' => 'Linked children, published results and permitted fee records.', 'url' => '/parent/login', 'sort_order' => 2],
            ['key' => 'teachers', 'title' => 'Teachers', 'description' => 'Assigned classes, subjects and score-entry tools.', 'url' => '/staff/login', 'sort_order' => 3],
            ['key' => 'school-staff', 'title' => 'School staff', 'description' => 'School administration tools according to role.', 'url' => '/staff/login', 'sort_order' => 4],
        ] as $portalLink) {
            PortalLink::query()->updateOrCreate(['key' => $portalLink['key']], $portalLink + ['status' => 'live']);
        }

        foreach ([
            ['driver' => 'paystack', 'display_name' => 'Paystack', 'supports_online_checkout' => true, 'supports_refunds' => true, 'configuration_status' => 'not_configured'],
            ['driver' => 'flutterwave', 'display_name' => 'Flutterwave', 'supports_online_checkout' => true, 'supports_refunds' => true, 'configuration_status' => 'not_configured'],
            ['driver' => 'moniepoint', 'display_name' => 'Moniepoint POS', 'supports_online_checkout' => false, 'supports_refunds' => false, 'configuration_status' => 'product_confirmation_required'],
        ] as $config) {
            $gateway = PaymentGatewayConfig::query()->firstOrNew(['driver' => $config['driver']]);
            if (! $gateway->exists) $gateway->is_enabled = false;
            $gateway->fill($config)->save();
        }

        $pages = [
            'about' => [
                'title' => 'About Abu Science College', 'eyebrow' => 'About Us',
                'excerpt' => 'A secondary school in Ugbe Akoko committed to academic excellence, scientific understanding, technology and responsible character.',
                'content_blocks' => [
                    ['type' => 'paragraph', 'text' => 'Abu Science College is a secondary educational institution located in Ugbe Akoko, Ondo State, Nigeria. The school is committed to providing quality education, promoting academic excellence, developing scientific knowledge and encouraging technological advancement.'],
                    ['type' => 'paragraph', 'text' => 'Our goal is to nurture intelligent, disciplined, confident and responsible students who are prepared to succeed in their academic pursuits and contribute positively to society. Education goes beyond classroom learning: we encourage curiosity, creativity, critical thinking, discipline and a strong reading culture.'],
                    ['type' => 'paragraph', 'text' => 'Our motto, “Readers Are Leaders”, reflects our belief that knowledge is the foundation of leadership and success.'],
                ],
            ],
            'history' => [
                'title' => 'Our History', 'eyebrow' => 'Our Story',
                'excerpt' => 'Abu Science College is located in Ugbe Akoko, Ondo State. Further history details will be added when confirmed by the school.',
                'content_blocks' => [
                    ['type' => 'paragraph', 'text' => 'Abu Science College is a secondary school in Ugbe Akoko, Ondo State, Nigeria. The school’s establishment history and other historical details have not yet been supplied. This page will be updated with verified information from the school administration.'],
                ],
            ],
            'mission-vision' => [
                'title' => 'Our Educational Philosophy', 'eyebrow' => 'What Guides Us',
                'excerpt' => 'Quality education, scientific and technological development, character, independent learning and preparation for higher education.',
                'content_blocks' => [
                    ['type' => 'heading', 'text' => 'Our Educational Philosophy'],
                    ['type' => 'paragraph', 'text' => 'We believe every child has the potential to achieve greatness when provided with quality education, proper guidance and a supportive learning environment.'],
                    ['type' => 'heading', 'text' => 'Our Commitments'],
                    ['type' => 'list', 'items' => ['Academic excellence', 'Scientific and technological development', 'Discipline and good character', 'Critical and creative thinking', 'Reading and independent learning', 'Leadership and personal development', 'Preparation for higher education']],
                    ['type' => 'heading', 'text' => 'Our Core Values'],
                    ['type' => 'list', 'items' => ['Excellence — striving for outstanding academic performance', 'Discipline — promoting responsible behaviour and self-control', 'Integrity — encouraging honesty and good character', 'Innovation — developing creative and problem-solving abilities', 'Knowledge — promoting continuous learning and reading', 'Leadership — preparing students to become responsible future leaders', 'Hard work — encouraging dedication and determination']],
                ],
            ],
            'jamb-achievers' => [
                'title' => 'JAMB Achievers', 'eyebrow' => 'Celebrating Achievement',
                'excerpt' => 'Students previously recognised for their JAMB performance, as supplied by Abu Science College.',
                'content_blocks' => [
                    ['type' => 'paragraph', 'text' => 'The following students were previously recognised for their JAMB performance.'],
                    ['type' => 'list', 'items' => ['Azeez Yusuf Olabanji — 250', 'Akande Oluwadunmininu Opeyemi — 197', 'Adediran Adegoriola Daniel — 188', 'Ani Victoria Chidinma — 189']],
                    ['type' => 'paragraph', 'text' => 'Scores are shown as provided by the school. Individual student photographs have not been matched to these names.'],
                ],
            ],
        ];
        foreach ($pages as $slug => $page) {
            CmsPage::query()->updateOrCreate(['slug' => $slug], [
                'title' => $page['title'], 'eyebrow' => $page['eyebrow'], 'excerpt' => $page['excerpt'],
                'content' => null, 'content_blocks' => $page['content_blocks'], 'status' => 'published', 'published_at' => now(),
            ]);
        }
    }
}
