<?php

namespace Database\Seeders;

use App\Domain\Website\Support\SiteSections;
use App\Models\AffectiveTrait;
use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\FooterSection;
use App\Models\HeroSlide;
use App\Models\MediaAsset;
use App\Models\NavigationItem;
use App\Models\PortalLink;
use App\Models\SchoolSettings;
use Illuminate\Database\Seeder;

/**
 * Brings the public website content in line with the approved designs.
 *
 * Safe to run more than once: new content is only created when missing, and the
 * one-time adjustments (renames, re-ordering, history layout) run a single time so
 * later edits made in the admin panel are never overwritten.
 */
class DesignRefreshSeeder extends Seeder
{
    private const MARKER = 'design_refresh_2026_10';

    public function run(): void
    {
        $settings = SchoolSettings::current();
        $firstRun = ! filled(data_get($settings->global_settings, self::MARKER));

        $this->sections();
        $this->branding($settings);
        $this->portalLinks($firstRun);
        $this->footer($firstRun);
        $this->navigation($firstRun);
        $this->heroSlide($firstRun);

        $this->once('results_defaults_2026_10', $settings, fn () => $this->affectiveTraits());
        $this->once('identity_texts_2026_10', $settings, fn () => $this->identityTexts($settings));

        if ($firstRun) {
            $this->history();
            $global = is_array($settings->global_settings) ? $settings->global_settings : [];
            $global[self::MARKER] = now()->toDateString();
            $settings->forceFill(['global_settings' => $global])->save();
        }
    }

    /** Run a block a single time, remembered in the global settings so admin deletions are respected. */
    private function once(string $key, SchoolSettings $settings, callable $callback): void
    {
        $settings->refresh();
        $global = is_array($settings->global_settings) ? $settings->global_settings : [];
        if (filled($global[$key] ?? null)) {
            return;
        }
        $callback();
        $global[$key] = now()->toDateString();
        $settings->forceFill(['global_settings' => $global])->save();
    }

    /** Moves the mission, vision, pledge and anthem from the old page blocks into School profile, where they are easy to edit. */
    private function identityTexts(SchoolSettings $settings): void
    {
        $page = CmsPage::query()->where('slug', 'mission-vision')->first();
        if (! $page) {
            return;
        }
        $groups = \App\Domain\Website\Support\PageBlocks::keyed(is_array($page->content_blocks) ? $page->content_blocks : []);
        $settings->refresh();
        $updates = [];
        if (! filled($settings->mission) && ! empty($groups['mission']['paragraphs'][0])) {
            $updates['mission'] = $groups['mission']['paragraphs'][0];
        }
        if (! filled($settings->vision) && ! empty($groups['vision']['paragraphs'][0])) {
            $updates['vision'] = $groups['vision']['paragraphs'][0];
        }
        if (! filled($settings->pledge) && ! empty($groups['pledge']['items'])) {
            $updates['pledge'] = implode("\n", $groups['pledge']['items']);
        }
        if (! filled($settings->anthem) && ! empty($groups['anthem']['items'])) {
            $updates['anthem'] = implode("\n", $groups['anthem']['items']);
        }
        if ($updates !== []) {
            $settings->forceFill($updates)->save();
        }
    }

    private function affectiveTraits(): void
    {
        $traits = ['Attendance', 'Attentiveness', 'Honesty', 'Industriousness', 'Initiative', 'Neatness', 'Relationship with others', 'Handwriting', 'Punctuality'];
        foreach ($traits as $index => $name) {
            AffectiveTrait::query()->firstOrCreate(['name' => $name], ['category' => 'affective', 'is_active' => true, 'sort_order' => $index + 1]);
        }
    }

    /** Fill empty branding fields only; anything set in the admin is never overwritten. */
    private function branding(SchoolSettings $settings): void
    {
        $updates = [];
        if (! filled($settings->logo_path) && is_file(storage_path('app/public/migrated-images/logo.jpg'))) {
            $updates['logo_path'] = 'migrated-images/logo.jpg';
        }
        if (! filled($settings->favicon_path) && is_file(storage_path('app/public/migrated-images/logo.jpg'))) {
            $updates['favicon_path'] = 'migrated-images/logo.jpg';
        }
        if (! filled($settings->description)) {
            $updates['description'] = 'Abu Science College is a secondary school in Ugbe Akoko, Ondo State, Nigeria, committed to quality education, academic excellence, scientific understanding and technological advancement.';
        }
        if ($updates !== []) {
            $settings->forceFill($updates)->save();
        }
    }

    private function media(string $filename): ?int
    {
        return MediaAsset::query()->where('path', 'migrated-images/'.$filename)->value('id');
    }

    private function sections(): void
    {
        $defaults = [
            'about.values' => ['title' => 'Our Core Values', 'description' => null, 'content' => [
                ['icon' => 'award', 'title' => 'Excellence', 'text' => 'We strive for outstanding academic performance.'],
                ['icon' => 'shield', 'title' => 'Discipline', 'text' => 'We promote responsible behaviour and self-control.'],
                ['icon' => 'checkCircle', 'title' => 'Integrity', 'text' => 'We encourage honesty and good character.'],
                ['icon' => 'lightbulb', 'title' => 'Innovation', 'text' => 'We develop creative and problem-solving abilities.'],
                ['icon' => 'book', 'title' => 'Knowledge', 'text' => 'We promote continuous learning and reading.'],
                ['icon' => 'users', 'title' => 'Leadership', 'text' => 'We prepare students to become responsible future leaders.'],
                ['icon' => 'trendingUp', 'title' => 'Hard Work', 'text' => 'We encourage dedication and determination.'],
            ]],
            'about.why' => ['title' => null, 'description' => null, 'content' => [
                ['icon' => 'cap', 'title' => 'Academic Excellence', 'text' => 'We encourage academic success through effective teaching and continuous learning.'],
                ['icon' => 'fileChart', 'title' => 'Early JAMB Preparation', 'text' => 'Early examination exposure helps students build confidence and familiarity with computer-based testing.'],
                ['icon' => 'flask', 'title' => 'Science Education', 'text' => 'We promote scientific knowledge, curiosity and practical understanding.'],
                ['icon' => 'laptop', 'title' => 'Technology Awareness', 'text' => 'We encourage students to develop ICT knowledge and technological skills.'],
                ['icon' => 'shield', 'title' => 'Discipline & Character', 'text' => 'We emphasise responsible behaviour, integrity and good conduct.'],
                ['icon' => 'arrow', 'title' => 'Future Readiness', 'text' => 'We aim to equip students with knowledge and confidence for higher education and future opportunities.'],
            ]],
            'academics.why' => ['title' => null, 'description' => null, 'content' => [
                ['icon' => 'book', 'title' => 'Junior Secondary', 'text' => 'JSS1, JSS2 and JSS3.'],
                ['icon' => 'cap', 'title' => 'Senior Secondary', 'text' => 'SS1, SS2 and SS3.'],
                ['icon' => 'flask', 'title' => 'Science & Technology', 'text' => 'Scientific understanding and technological awareness are part of the school’s focus.'],
            ]],
            'academics.curriculum' => ['title' => null, 'description' => 'Subject categories shared by the school; the final subject list is to be confirmed by the school administration.', 'content' => [
                ['icon' => 'flask', 'title' => 'Sciences', 'text' => 'Mathematics, Biology, Chemistry, Physics and Agricultural Science.'],
                ['icon' => 'laptop', 'title' => 'Technology and ICT', 'text' => 'Computer Studies, Information Technology and Basic Technology.'],
                ['icon' => 'book', 'title' => 'Languages and Humanities', 'text' => 'English Language, Civic Education, Social Studies and Christian Religious Studies.'],
            ]],
            'academics.approach' => ['title' => null, 'description' => 'We encourage curiosity, creativity, critical thinking, discipline and independent learning.', 'content' => [
                ['title' => 'Reading and independent learning'],
                ['title' => 'Critical and creative thinking'],
                ['title' => 'Scientific and technological development'],
                ['title' => 'Preparation for higher education'],
            ]],
            'admissions.steps' => ['title' => null, 'description' => null, 'content' => []],
            'admissions.levels' => ['title' => 'Academic Levels', 'description' => null, 'content' => [
                ['title' => 'Junior Secondary (JSS1–JSS3)', 'text' => 'Junior secondary education.', 'media_id' => $this->media('students-group.jpg'), 'url' => '/academics/junior-secondary'],
                ['title' => 'Senior Secondary (SS1–SS3)', 'text' => 'Senior secondary education.', 'media_id' => $this->media('textbooks.jpg'), 'url' => '/academics/senior-secondary'],
            ]],
            'cta.admissions' => ['title' => 'Admissions · 2026/2027', 'description' => 'Contact the school to confirm availability, requirements, procedures and fees.', 'content' => []],
            'cta.join' => ['title' => 'Admissions are open', 'description' => 'Opportunities for JSS1 and SS1 are listed for the 2026/2027 academic session. Contact the school to confirm details.', 'content' => []],
            'cta.academics' => ['title' => 'Building Future Leaders', 'description' => 'Knowledge, science and technology.', 'content' => []],
            'mission.closing' => ['title' => null, 'description' => 'READERS ARE LEADERS — knowledge is the foundation of leadership and success.', 'content' => []],
        ];

        $order = 0;
        foreach ($defaults as $key => $values) {
            if (! array_key_exists($key, SiteSections::KEYS)) {
                continue;
            }
            CmsSection::query()->firstOrCreate(
                ['page_id' => null, 'section_key' => $key],
                $values + ['is_visible' => true, 'sort_order' => $order++],
            );
        }
    }

    private function portalLinks(bool $firstRun): void
    {
        $links = [
            ['key' => 'results', 'title' => 'Results Portal', 'description' => 'Check your results online', 'url' => '/login', 'sort_order' => 1],
            ['key' => 'admission', 'title' => 'Admission Portal', 'description' => 'Apply for admission online', 'url' => '/admissions', 'sort_order' => 2],
            ['key' => 'students', 'title' => 'Student Portal', 'description' => 'Access student resources', 'url' => '/login', 'sort_order' => 3],
            ['key' => 'staff', 'title' => 'Staff Portal', 'description' => 'Access staff resources', 'url' => '/login', 'sort_order' => 4],
            ['key' => 'parents', 'title' => 'Parent Portal', 'description' => 'Access parent resources', 'url' => '/login', 'sort_order' => 5],
        ];
        foreach ($links as $link) {
            $existing = PortalLink::query()->where('key', $link['key'])->first();
            if (! $existing) {
                PortalLink::query()->create($link + ['status' => 'live']);
            } elseif ($firstRun) {
                $existing->fill(['title' => $link['title'], 'description' => $link['description'], 'sort_order' => $link['sort_order']])->save();
            }
        }
        if ($firstRun) {
            PortalLink::query()->whereIn('key', ['teachers', 'school-staff'])->update(['status' => 'disabled']);
        }
    }

    private function footer(bool $firstRun): void
    {
        $sections = [
            ['Quick Links', [['label' => 'Home', 'url' => '/'], ['label' => 'About Us', 'url' => '/about'], ['label' => 'Academics', 'url' => '/academics'], ['label' => 'Admissions', 'url' => '/admissions'], ['label' => 'News & Events', 'url' => '/news'], ['label' => 'Gallery', 'url' => '/gallery'], ['label' => 'Contact Us', 'url' => '/contact']], 0],
            ['Portals', [['label' => 'Results Portal', 'url' => '/login'], ['label' => 'Admission Portal', 'url' => '/admissions'], ['label' => 'Student Portal', 'url' => '/login'], ['label' => 'Staff Portal', 'url' => '/login'], ['label' => 'Parent Portal', 'url' => '/login']], 1],
        ];
        foreach ($sections as [$title, $links, $sort]) {
            FooterSection::query()->firstOrCreate(['title' => $title], ['links' => $links, 'is_visible' => true, 'sort_order' => $sort]);
        }
        if ($firstRun) {
            FooterSection::query()->whereIn('title', ['Explore', 'Admissions', 'School Portal'])->update(['is_visible' => false]);
        }
    }

    private function navigation(bool $firstRun): void
    {
        if (! $firstRun) {
            return;
        }
        NavigationItem::query()->where('menu', 'main')->where('label', 'About')->update(['label' => 'About Us']);
        NavigationItem::query()->where('menu', 'main')->where('label', 'Mission, Vision & Pledge')->update(['label' => 'Mission, Vision, Pledge & Anthem']);
        NavigationItem::query()->where('menu', 'main')->where('label', 'Contact')->update(['label' => 'Contact']);
        NavigationItem::query()->where('menu', 'main')->where('label', 'FAQ')->update(['is_visible' => false]);
    }

    private function heroSlide(bool $firstRun): void
    {
        $heading = 'Readers Are Leaders';
        if (HeroSlide::query()->where('heading', $heading)->exists()) {
            return;
        }
        $imageId = $this->media('school-gate.jpg');
        $path = $imageId ? 'migrated-images/school-gate.jpg' : null;
        HeroSlide::query()->increment('sort_order');
        HeroSlide::query()->create([
            'eyebrow' => null,
            'heading' => $heading,
            'body' => 'Building future leaders through knowledge, science and technology. Abu Science College is committed to academic excellence, curiosity and responsible character.',
            'image_id' => $imageId,
            'image_path' => $path,
            'image_alt' => 'Abu Science College campus building',
            'cta_label' => 'Discover More',
            'cta_url' => '/about',
            'is_enabled' => true,
            'sort_order' => 0,
        ]);
    }

    private function history(): void
    {
        $page = CmsPage::query()->where('slug', 'history')->first();
        if (! $page) {
            return;
        }
        $page->content_blocks = [
            ['type' => 'paragraph', 'text' => 'Abu Science College is a secondary school in Ugbe Akoko, Ondo State, Nigeria. The school’s establishment history and other historical details have not yet been supplied. This page will be updated with verified information from the school administration.'],
        ];
        $page->metadata = array_merge(is_array($page->metadata) ? $page->metadata : [], ['layout' => 'chapters']);
        $page->save();
    }
}
