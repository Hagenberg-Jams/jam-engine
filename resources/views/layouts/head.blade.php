<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $page->title() }}</title>

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

{{-- App Meta Tags --}}
@include('hyde::layouts.meta')

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

{{-- Add any extra code to include before the closing <head> tag --}}
@stack('head')

{{-- If the user has defined any custom head tags, render them here --}}
{!! config('hyde.head') !!}
{!! Includes::html('head') !!}

