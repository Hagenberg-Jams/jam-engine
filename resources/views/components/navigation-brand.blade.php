@php
    $siteConfig = \App\GameJamData::getSiteConfig();
    $logoLight = $siteConfig['site_logo_light'] ?? 'hagenberg_game_jam_logo_white.svg';
    $logoDark = $siteConfig['site_logo_dark'] ?? 'hagenberg_game_jam_logo_black.svg';
    // Clean up logo paths - remove leading slash and assets/images/ prefix
    $logoLight = ltrim($logoLight, '/');
    $logoLight = str_replace(['jekyll-site/assets/images/', 'assets/images/'], '', $logoLight);
    $logoDark = ltrim($logoDark, '/');
    $logoDark = str_replace(['jekyll-site/assets/images/', 'assets/images/'], '', $logoDark);
@endphp
<a href="{{ Routes::find('index') }}" class="font-bold px-4 flex items-center" aria-label="Home page">
    <img src="/media/{{ $logoDark }}" alt="{{ config('hyde.name', 'Hagenberg Game Jam') }}" class="h-12 dark:hidden">
    <img src="/media/{{ $logoLight }}" alt="{{ config('hyde.name', 'Hagenberg Game Jam') }}" class="h-12 hidden dark:block">
</a>

