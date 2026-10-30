@php
    $logoLight = ltrim((string) config('jam.branding.logo_light', ''), '/');
    $logoDark = ltrim((string) config('jam.branding.logo_dark', ''), '/');
@endphp
<a href="/" class="font-bold px-4 flex items-center" aria-label="Home page">
    @if($logoDark && $logoLight)
        <img src="/media/{{ $logoDark }}" alt="{{ config('hyde.name') }}" width="48" height="48" class="h-12 dark:hidden">
        <img src="/media/{{ $logoLight }}" alt="{{ config('hyde.name') }}" width="48" height="48" class="h-12 hidden dark:block">
    @else
        <span class="text-lg">{{ config('hyde.name') }}</span>
    @endif
</a>

