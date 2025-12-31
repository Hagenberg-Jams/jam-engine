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
    <header class="{{ $__env->hasSection('header') ? 'h-screen flex flex-col' : '' }}">
        @if(view()->exists('layouts.navigation'))
            @include('layouts.navigation')
        @else
            @include('hyde::layouts.navigation')
        @endif

        @hasSection('header')
            <div class="flex-1 min-h-0">
                @yield('header')
            </div>
        @endif
    </header>

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

    <!-- Scroll to Top Button -->
    <button id="scroll-top" class="fixed bottom-8 right-8 bg-indigo-600 hover:bg-indigo-700 text-white p-4 rounded-full shadow-lg hidden transition-all z-50" aria-label="Back to top">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"></path>
        </svg>
    </button>
</body>
</html>

