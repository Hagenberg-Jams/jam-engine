@php
    $logoLight = ltrim((string) config('gamejam.branding.logo_light', 'hagenberg_game_jam_logo_white.svg'), '/');
    $logoDark = ltrim((string) config('gamejam.branding.logo_dark', 'hagenberg_game_jam_logo_black.svg'), '/');
@endphp
<a href="/" class="font-bold px-4 flex items-center" aria-label="Home page">
    <img src="/media/{{ $logoDark }}" alt="{{ config('hyde.name', 'Hagenberg Game Jam') }}" width="48" height="48" class="h-12 dark:hidden">
    <img src="/media/{{ $logoLight }}" alt="{{ config('hyde.name', 'Hagenberg Game Jam') }}" width="48" height="48" class="h-12 hidden dark:block">
</a>

