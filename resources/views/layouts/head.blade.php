<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $page->title() }}</title>

{{-- Preload critical assets to reduce request chain (discover earlier, fetch in parallel) --}}
@if(!Vite::running() && !config('hyde.load_app_styles_from_cdn', false))
    @if(Asset::exists('app.css'))
        @php $appCss = '/media/app.css' . (file_exists(base_path('_media/app.css')) ? '?v=' . substr(md5_file(base_path('_media/app.css')), 0, 8) : ''); @endphp
        <link rel="preload" href="{{ $appCss }}" as="style">
    @endif
    @if(Asset::exists('app.js'))
        @php $appJs = '/media/app.js' . (file_exists(base_path('_media/app.js')) ? '?v=' . substr(md5_file(base_path('_media/app.js')), 0, 8) : ''); @endphp
        <link rel="modulepreload" href="{{ $appJs }}">
    @endif
@endif

{{-- Favicons --}}
@if (Asset::exists('favicon.ico'))
    <link rel="icon" type="image/x-icon" href="/media/favicon.ico">
@endif
@if (Asset::exists('favicon-16x16.png'))
    <link rel="icon" type="image/png" sizes="16x16" href="/media/favicon-16x16.png">
@endif
@if (Asset::exists('favicon-32x32.png'))
    <link rel="icon" type="image/png" sizes="32x32" href="/media/favicon-32x32.png">
@endif
@if (Asset::exists('apple-touch-icon.png'))
    <link rel="apple-touch-icon" sizes="180x180" href="/media/apple-touch-icon.png">
@endif
@if (Asset::exists('android-chrome-192x192.png'))
    <link rel="icon" type="image/png" sizes="192x192" href="/media/android-chrome-192x192.png">
@endif
@if (Asset::exists('android-chrome-512x512.png'))
    <link rel="icon" type="image/png" sizes="512x512" href="/media/android-chrome-512x512.png">
@endif
@if (Asset::exists('site.webmanifest'))
    <link rel="manifest" href="/media/site.webmanifest">
@endif

{{-- App Meta Tags (custom meta excludes invalid rel="sitemap" link) --}}
@include('layouts.meta')

{{-- Open Graph / Twitter Card Meta Tags --}}
@php
    // Collect context variables for OG meta tags
    $ogContext = [
        'year' => $year ?? null,
        'gameName' => $gameName ?? null,
        'description' => $description ?? null,
        'headerImage' => $headerImage ?? null,
        'personName' => $personName ?? null,
        'totalGames' => $totalGames ?? 0,
        'years' => $years ?? [],
        'persons' => $persons ?? null,
        'jam' => $jam ?? null,
        'games' => $games ?? null,
    ];
    
    $ogMeta = (new \App\Helpers\OpenGraphMeta())->getMetaTags($ogContext);
@endphp

{{-- Meta Description (separate from OG for better SEO) --}}
<meta name="description" content="{{ $ogMeta['description'] }}">

{{-- Canonical URL --}}
<link rel="canonical" href="{{ $ogMeta['url'] }}">

{{-- Open Graph Tags --}}
<meta property="og:type" content="{{ $ogMeta['type'] }}">
<meta property="og:title" content="{{ $ogMeta['title'] }}">
<meta property="og:description" content="{{ $ogMeta['description'] }}">
<meta property="og:image" content="{{ $ogMeta['image'] }}">
<meta property="og:url" content="{{ $ogMeta['url'] }}">
<meta property="og:site_name" content="{{ $ogMeta['site_name'] ?? 'Hagenberg Game Jam' }}">

{{-- Twitter Card Tags --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $ogMeta['title'] }}">
<meta name="twitter:description" content="{{ $ogMeta['description'] }}">
<meta name="twitter:image" content="{{ $ogMeta['image'] }}">

{{-- App Stylesheets --}}
@if(view()->exists('layouts.styles'))
    @include('layouts.styles')
@else
    @include('hyde::layouts.styles')
@endif

@if(Features::hasDarkmode())
    {{-- Check the local storage for theme preference to avoid FOUC --}}
    <meta id="meta-color-scheme" name="color-scheme" content="{{ config('hyde.default_color_scheme', 'light') }}">
    <script>if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) { document.documentElement.classList.add('dark'); document.getElementById('meta-color-scheme').setAttribute('content', 'dark');} else { document.documentElement.classList.remove('dark') } </script>
@endif

{{-- Page-specific head (e.g. LCP preload for homepage) --}}
@yield('head')

{{-- Add any extra code to include before the closing <head> tag --}}
@stack('head')

{{-- If the user has defined any custom head tags, render them here --}}
{!! config('hyde.head') !!}
{!! Includes::html('head') !!}

