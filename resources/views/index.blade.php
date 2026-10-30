@extends('layouts.app')

@php
    $latestJam = config('jam.latest_jam', '');
    $votingActive = (bool) config('jam.voting.active', false);
    $votingUrl = config('jam.voting.url', '');
    $nextJam = config('jam.registration.next_jam');
    $registrationActive = (bool) config('jam.registration.active', false);
    $registrationUrl = config('jam.registration.url', '');

    $homepage = \HagenbergJams\JamEngine\JamData::getHomepage();
    $hero = $homepage['hero'] ?? [];
    $heroImages = $hero['images'] ?? [];
    $highlights = $homepage['highlights'] ?? [];
    $gallery = $homepage['gallery'] ?? [];
    $latestHasProjects = $latestJam !== '' && \HagenbergJams\JamEngine\JamData::getProjects((int) $latestJam) !== [];
    $upcomingJam = config('jam.features.upcoming_jam') ? \HagenbergJams\JamEngine\JamSchedule::upcoming() : null;
    $about = $homepage['about'] ?? [];
    $video = $homepage['video'] ?? [];
    $sponsors = $homepage['sponsors'] ?? [];

    $firstHeroImage = ($heroImages[0] ?? '') ? (str_starts_with($heroImages[0], '/') ? $heroImages[0] : '/media/' . ltrim($heroImages[0], '/')) : '';
@endphp

@section('head')
@if($firstHeroImage)
    <link rel="preload" as="image" href="{{ $firstHeroImage }}" fetchpriority="high">
@endif
@endsection

<!-- Hero Section with Slider -->
@section('header')
<section class="relative h-full flex items-center justify-center overflow-hidden">
    <div class="hero-slider-container absolute inset-0 w-full h-full z-0">
        <div class="hero-slider-track relative w-full h-full">
            @foreach($heroImages as $index => $image)
                @php
                    $imageSrc = str_starts_with($image, '/') ? $image : '/media/' . ltrim($image, '/');
                @endphp
                <div class="hero-slide {{ $index === 0 ? 'active' : '' }}" data-slide="{{ $index }}" @if($index !== 0) style="background-image: url('{{ $imageSrc }}');" @endif>
                    @if($index === 0)
                        {{-- LCP: use <img> for first slide so it's discoverable in HTML, with fetchpriority=high --}}
                        <img src="{{ $imageSrc }}" alt="" class="hero-slide-img" fetchpriority="high" loading="eager" width="1920" height="1080">
                    @endif
                </div>
            @endforeach
        </div>
        <div class="absolute inset-0 flex items-center justify-center z-20 pointer-events-none">
            <div class="container mx-auto px-4 text-center text-white drop-shadow-lg">
                <hgroup>
                    @if(isset($hero['subtitle']) && $hero['subtitle'])
                    <p class="text-lg md:text-xl mb-4">{{ $hero['subtitle'] }}</p>
                    @endif
                    <h1 class="text-4xl md:text-6xl lg:text-7xl font-bold mb-4">{{ $hero['title'] ?? config('hyde.name') }}</h1>
                    @if(isset($hero['description']) && $hero['description'])
                    <p class="text-lg md:text-xl mb-8 max-w-3xl mx-auto mt-8">{{ $hero['description'] }}</p>
                    @endif
                </hgroup>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    @if($latestHasProjects)
                    <a href="/{{ $latestJam }}" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg font-semibold transition-colors pointer-events-auto">
                        {{ config('jam.labels.latest_cta') }} {{ $latestJam }}
                    </a>
                    @endif
                    @if($votingActive)
                    <a href="{{ $votingUrl }}" target="_blank" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg font-semibold transition-colors pointer-events-auto">
                        {{ config('jam.labels.vote_cta') }} {{ $latestJam }}
                    </a>
                    @endif
                    @if($registrationActive && $nextJam && !$upcomingJam)
                    <a href="{{ $registrationUrl }}" target="_blank" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-semibold transition-colors pointer-events-auto">
                        Register for {{ $nextJam }}
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('content')

@include('components.upcoming-jam')

<!-- Categories Section -->
@if(count($highlights) > 0)
@php
    $highlightIcons = ['document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', 'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'bolt' => 'M13 10V3L4 14h7v7l9-11h-7z'];
@endphp
<section class="py-16 bg-gray-50 dark:bg-gray-800" aria-labelledby="categories-heading">
    <div class="container mx-auto px-4">
        <h2 id="categories-heading" class="sr-only">What makes the {{ config('hyde.name') }} special</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($highlights as $highlight)
            <div class="bg-gray-200 dark:bg-gray-700 p-8 rounded-lg shadow-lg text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-white dark:bg-primary-900 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $highlightIcons[$highlight['icon'] ?? 'bolt'] ?? $highlightIcons['bolt'] }}"></path>
                    </svg>
                </div>
                <div class="w-3 h-3 mx-auto mb-4 bg-primary-600 rounded-full"></div>
                <h3 class="text-2xl font-bold mb-4 dark:text-white">{{ $highlight['title'] ?? '' }}</h3>
                <p class="text-gray-600 dark:text-gray-300">{{ $highlight['text'] ?? '' }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- About Section -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div class="order-2 lg:order-1 flex items-center">
                @if(isset($about['gallery']) && is_array($about['gallery']) && count($about['gallery']) > 0)
                    {{-- Masonry Gallery --}}
                    <div class="masonry-gallery w-full">
                        @foreach($about['gallery'] as $index => $galleryItem)
                            @php
                                // Support both old format (string) and new format (object)
                                $imageFile = is_array($galleryItem) ? ($galleryItem['image'] ?? '') : $galleryItem;
                                $description = is_array($galleryItem) ? ($galleryItem['description'] ?? '') : '';
                                
                                $imageSrc = ($imageFile !== '' && str_starts_with($imageFile, '/')) ? $imageFile : '/media/' . ltrim($imageFile, '/');
                                // Responsive variants (400w, 800w) generated at build time
                                $variants = \HagenbergJams\JamEngine\Services\OptimizeImagesService::getResponsiveVariants($imageFile);
                                $imgSrc = !empty($variants[400]) ? '/media/' . $variants[400] : $imageSrc;
                                $srcset = !empty($variants)
                                    ? implode(', ', array_map(fn ($w, $v) => '/media/' . $v . ' ' . $w . 'w', array_keys($variants), $variants))
                                    : null;
                                // Create masonry pattern: first and every 4th image spans 2 rows
                                $rowSpan = ($index === 0 || ($index + 1) % 4 === 0) ? 'row-span-2' : 'row-span-1';
                                
                                // Use description for both alt and Lightbox
                                $glightboxData = 'title: ' . $description;
                            @endphp
                            <a href="{{ $imageSrc }}" 
                               class="masonry-item {{ $rowSpan }} glightbox" 
                               data-glightbox="{{ $glightboxData }}">
                                <img src="{{ $imgSrc }}" 
                                     @if($srcset)srcset="{{ $srcset }}"
                                     sizes="(max-width: 768px) 100vw, 356px"@endif
                                     alt="{{ $description }}" 
                                     class="masonry-image">
                            </a>
                        @endforeach
                    </div>
                @else
                    {{-- A single image, such as a poster, takes the place of the gallery --}}
                    @if(!empty($about['image']))
                        <img src="/media/{{ ltrim($about['image'], '/') }}" alt="{{ $about['image_alt'] ?? '' }}" class="rounded-lg shadow-lg w-full">
                    @endif
                @endif
            </div>
            <div class="order-1 lg:order-2">
                <span class="inline-block w-3 h-3 bg-primary-600 rounded-full"></span>
                <span class="text-primary-600 dark:text-primary-400 font-semibold uppercase tracking-wide ml-2">{{ $about['eyebrow'] ?? 'About' }}</span>
                <h2 class="text-4xl font-bold mt-4 mb-6 dark:text-white">{{ $about['title'] ?? config('hyde.name') }}</h2>
                @if(!empty($about['subtitle']))
                <h3 class="text-2xl font-semibold mb-6 dark:text-gray-200">{{ $about['subtitle'] }}</h3>
                @endif
                <div class="prose dark:prose-invert max-w-none">
                    {!! \Illuminate\Support\Str::markdown((string) ($about['markdown'] ?? '')) !!}
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Gallery Section -->
@if(count($gallery) > 0)
<section class="py-16 bg-gray-50 dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            @foreach($gallery as $galleryItem)
                @php
                    $galleryImage = is_array($galleryItem) ? ($galleryItem['image'] ?? '') : $galleryItem;
                    $galleryText = is_array($galleryItem) ? ($galleryItem['description'] ?? '') : '';
                @endphp
                <a href="/media/{{ ltrim($galleryImage, '/') }}" class="glightbox block overflow-hidden rounded-lg shadow-lg" data-glightbox="title: {{ $galleryText }}">
                    <img src="/media/{{ ltrim($galleryImage, '/') }}" alt="{{ $galleryText }}" loading="lazy" class="w-full h-64 object-cover hover:scale-105 transition-transform duration-300">
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Video Section -->
@if(!empty($video['youtube_id']))
<section id="youtube-video-section" class="relative py-16 bg-gray-900 dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-end mb-12">
            <div class="text-right">
                <span class="text-primary-400 uppercase tracking-wide text-sm">{{ $video['eyebrow'] ?? 'intro video' }}</span>
                <h2 class="text-4xl md:text-5xl font-bold text-white mt-2">{!! nl2br(e($video['title'] ?? config('hyde.name')), false) !!}</h2>
            </div>
            <div>
                <p class="text-gray-200 text-lg">{{ $video['description'] ?? '' }}</p>
            </div>
        </div>
        <div class="max-w-4xl mx-auto">
            @include('components.youtube-embed', ['youtubeId' => $video['youtube_id'], 'title' => config('hyde.name') . ' Intro Video'])
        </div>
    </div>
</section>
@endif

<!-- Sponsors Section -->
@if(count($sponsors['items'] ?? []) > 0)
<section class="py-16 bg-gray-50 dark:bg-gray-200">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-4 text-gray-800">{{ $sponsors['title'] ?? 'Our Sponsors' }}</h2>
        @if(isset($sponsors['description']) && $sponsors['description'])
        <p class="text-center text-gray-600 dark:text-gray-700 mb-8">{{ $sponsors['description'] }}</p>
        @endif
        <div class="sponsors-slider swiper max-w-6xl mx-auto">
            <div class="swiper-wrapper">
                @foreach(($sponsors['items'] ?? []) as $sponsor)
                    @php
                        $logo = (string) ($sponsor['logo'] ?? '');
                        $logoSrc = ($logo !== '' && str_starts_with($logo, '/')) ? $logo : '/media/' . ltrim($logo, '/');
                        $logoWidth = (int) ($sponsor['width'] ?? 200);
                        $logoHeight = (int) ($sponsor['height'] ?? 64);
                    @endphp
                    <div class="swiper-slide">
                        <div class="flex justify-center items-center h-32">
                            <a href="{{ $sponsor['url'] ?? '#' }}" target="_blank"
                               class="opacity-100 hover:opacity-70 transition-opacity">
                                <img src="{{ $logoSrc }}" alt="{{ $sponsor['name'] ?? 'Sponsor' }}"
                                     width="{{ $logoWidth }}" height="{{ $logoHeight }}" class="h-16 object-contain">
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
@endsection
