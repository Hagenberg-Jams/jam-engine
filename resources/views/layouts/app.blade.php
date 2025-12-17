<!DOCTYPE html>
<html lang="{{ config('hyde.language', 'de') }}">
<head>
    @if(view()->exists('layouts.head'))
        @include('layouts.head')
    @else
        @include('hyde::layouts.head')
    @endif
</head>
<body id="app" class="flex flex-col min-h-screen overflow-x-hidden dark:bg-gray-900 dark:text-white" x-data="{ navigationOpen: false }" x-on:keydown.escape="navigationOpen = false;">
    @include('hyde::components.skip-to-content-button')
    @if(view()->exists('layouts.navigation'))
        @include('layouts.navigation')
    @else
        @include('hyde::layouts.navigation')
    @endif

    <main id="content" class="flex-1">
        @yield('content')
    </main>

    @if(view()->exists('layouts.footer'))
        @include('layouts.footer')
    @else
        @include('hyde::layouts.footer')
    @endif

    @if(view()->exists('layouts.scripts'))
        @include('layouts.scripts')
    @else
        @include('hyde::layouts.scripts')
    @endif
</body>
</html>

