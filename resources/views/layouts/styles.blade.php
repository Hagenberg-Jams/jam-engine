{{-- Mobile nav: show links when nav is open; dropdown chevron rotation --}}
<style>
@media (max-width: 767px) {
    #main-navigation.mobile-nav-open #main-navigation-links { display: block !important; }
}
/* Chevron rotate when dropdown is open */
.dropdown-container details[open] .dropdown-chevron { transform: rotate(180deg); }
</style>

{{-- The compiled Tailwind/App styles --}}
@if(Vite::running())
    {{ Vite::assets(['resources/assets/app.css']) }}
@else
    @if(config('hyde.load_app_styles_from_cdn', false))
        <link rel="stylesheet" href="{{ HydeFront::cdnLink('app.css') }}">
    @elseif(Asset::exists('app.css'))
        @php
            // Use relative path for development server compatibility
            $cssPath = '/media/app.css';
            $cacheBust = file_exists(base_path('_media/app.css')) ? '?v=' . substr(md5_file(base_path('_media/app.css')), 0, 8) : '';
        @endphp
        <link rel="stylesheet" href="{{ $cssPath }}{{ $cacheBust }}">
    @endif


    {{-- Dynamic TailwindCSS Play CDN --}}
    @if(config('hyde.use_play_cdn', false))
        <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
        <script>tailwind.config = { {!! HydeFront::injectTailwindConfig() !!} }</script>
        <script>console.warn('The HydePHP TailwindCSS Play CDN is enabled. This is for development purposes only and should not be used in production.', 'See https://hydephp.com/docs/1.x/managing-assets');</script>
    @endif
@endif

{{-- Add any extra styles to include after the others --}}
@stack('styles')

