@php
    /** @var string $label */
    /** @var \Illuminate\Support\Collection|\Hyde\Framework\Features\Navigation\NavigationMenu|null $items */
    $routeKey = \Hyde\Foundation\Facades\Routes::has($label) ? $label : null;
@endphp

<div class="dropdown-container relative" x-data="{ open: false }">
    <div class="flex items-center gap-1">
        @if($routeKey)
            <a href="{{ (string) \Hyde\Foundation\Facades\Routes::get($routeKey) }}"
               class="block my-2 md:my-0 md:inline-block py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100">
                {{ $label }}
            </a>
        @else
            <span class="block my-2 md:my-0 md:inline-block py-1 text-gray-700 dark:text-gray-100">
                {{ $label }}
            </span>
        @endif

        <button type="button"
                class="dropdown-button inline-flex items-center py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100"
                aria-label="Toggle dropdown"
                x-on:click="open = ! open" @click.outside="open = false" @keydown.escape.window="open = false">
            <svg class="inline transition-all dark:fill-white"
                 x-bind:style="open ? { transform: 'rotate(180deg)' } : ''"
                 xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000">
                <path d="M0 0h24v24H0z" fill="none"/>
                <path d="M7 10l5 5 5-5z"/>
            </svg>
        </button>
    </div>

    <div class="dropdown absolute shadow-lg bg-white dark:bg-gray-700 z-50 right-0" :class="open ? '' : 'hidden'" x-cloak="">
        <ul class="dropdown-items px-3 py-2">
            @isset($items)
                @foreach ($items as $item)
                    <li class="whitespace-nowrap">
                        <x-hyde::navigation.navigation-link :item="$item"/>
                    </li>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </ul>
    </div>
</div>


