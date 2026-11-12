@extends('site.layout')
@section('title', ($seoTitle ?? 'Our History').' | '.$settings->short_name)
@section('description', $seoDescription ?? $settings->description)
@section('content')
<x-page-hero eyebrow="Our history" title="Our History" :lead="$page?->excerpt ?: 'History information will be published when it has been confirmed by the school.'" :image="$heroImage" :crumbs="[['Home', route('home')], ['History', null]]" />

<section class="section">
    <div class="site-container stack" style="--gap:1.5rem">
        @forelse($chapters as $chapter)
            @php
                $chapterImage = $chapter['media_id'] && isset($contentMedia[$chapter['media_id']]) ? $contentMedia[$chapter['media_id']]->url() : \App\Support\MediaUrl::for('migrated-images/'.($loop->first ? 'students-reading.jpg' : 'students-group.jpg'));
                $hasText = count($chapter['paragraphs']) > 0;
            @endphp
            <article class="history-card">
                @if($chapter['title'] !== '')<h2 class="section-title">{{ $chapter['title'] }}</h2>@endif
                <div class="history-grid{{ $loop->first ? '' : ' history-grid--flip' }}">
                    @if($loop->first)<div class="photo-frame"><img src="{{ $chapterImage }}" alt="" loading="lazy" decoding="async"></div>@endif
                    <div>
                        <div class="history-text">
                            @foreach($chapter['paragraphs'] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
                            @if(count($chapter['bullets']))
                                <ul class="dot-list dot-list--maroon">@foreach($chapter['bullets'] as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
                            @endif
                        </div>
                        @if(count($chapter['rosters']))
                            <div class="roster">
                                @foreach($chapter['rosters'] as $roster)
                                    <div class="roster__box">
                                        <h3 style="margin:0 0 .6rem;display:flex;align-items:center;gap:.6rem;font:700 .85rem/1.2 var(--font-sans);letter-spacing:.04em;text-transform:uppercase;color:var(--maroon-700)"><x-site-icon :name="$loop->first ? 'users' : 'user'" />{{ $roster['title'] }}</h3>
                                        <ol class="numbered{{ count($roster['items']) > 8 ? ' roster__cols' : '' }}">@foreach($roster['items'] as $name)<li>{{ $name }}</li>@endforeach</ol>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if($chapter['quote'])
                            <div class="prayer"><x-site-icon name="quote" /><p>{{ $chapter['quote'] }}</p></div>
                        @endif
                    </div>
                    @unless($loop->first)<div class="photo-frame"><img src="{{ $chapterImage }}" alt="" loading="lazy" decoding="async"></div>@endunless
                </div>
            </article>
        @empty
            <p class="empty-state">Our history is being prepared. Please check back soon.</p>
        @endforelse

        <div class="band band--maroon">
            <div class="band__lead"><span class="band__icon"><x-site-icon name="cap" /></span><div><h2 class="band__title">{{ \App\Domain\Website\Support\SiteSections::title('cta.admissions', 'Learn more about Abu Science College.') }}</h2><p class="band__text">{{ \App\Domain\Website\Support\SiteSections::description('cta.admissions', 'Contact the school for confirmed history and admissions information.') }}</p></div></div>
            <a class="btn btn--gold" href="{{ route('admissions') }}">Apply now <x-site-icon name="arrow" /></a>
        </div>
    </div>
</section>
@endsection
