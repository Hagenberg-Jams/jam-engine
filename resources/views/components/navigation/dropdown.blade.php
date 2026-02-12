@php
    /** @var string $label */
    /** @var \Illuminate\Support\Collection|\Hyde\Framework\Features\Navigation\NavigationMenu|null $items */
    $routeKey = \Hyde\Foundation\Facades\Routes::has($label) ? $label : null;
@endphp

<div class="dropdown-container" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    {{-- Mobile: Simple list under the label --}}
    <div class="md:hidden">
        <button type="button"
                class="flex items-center gap-1 w-full my-2 py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100 cursor-pointer"
                x-on:click="open = ! open"
                aria-haspopup="true"
                :aria-expanded="open">
            <span>{{ $label }}</span>
            <svg class="inline transition-all dark:fill-white"
                 x-bind:style="open ? { transform: 'rotate(180deg)' } : ''"
                 xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000">
                <path d="M0 0h24v24H0z" fill="none"/>
                <path d="M7 10l5 5 5-5z"/>
            </svg>
        </button>
        <ul x-show="open" x-cloak class="ml-4 space-y-1">
            @isset($items)
                @foreach ($items as $item)
                    <li>
                        <x-navigation.navigation-link :item="$item"/>
                    </li>
                @endforeach
            @else
                {{ $slot }}
            @endif
        </ul>
    </div>

    {{-- Desktop: Dropdown with absolute positioning --}}
    <div class="hidden md:block relative">
        <div class="flex items-center gap-1">
            <button type="button"
                    class="block my-2 md:my-0 md:inline-block py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100 cursor-pointer"
                    x-on:click="open = ! open"
                    aria-haspopup="true"
                    :aria-expanded="open">
                {{ $label }}
            </button>

            <button type="button"
                    class="dropdown-button inline-flex items-center py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100"
                    aria-label="{{ $label }} archive – expand to view years"
                    x-on:click="open = ! open">
                <svg class="inline transition-all dark:fill-white"
                     x-bind:style="open ? { transform: 'rotate(180deg)' } : ''"
                     xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000">
                    <path d="M0 0h24v24H0z" fill="none"/>
                    <path d="M7 10l5 5 5-5z"/>
                </svg>
            </button>
        </div>

        <div class="dropdown absolute shadow-lg bg-white dark:bg-gray-700 z-50 right-0" 
             :class="open ? '' : 'hidden'" 
             x-cloak="">
            <ul class="dropdown-items px-3 py-2">
                @isset($items)
                    @foreach ($items as $item)
                        <li class="whitespace-nowrap">
                            <x-navigation.navigation-link :item="$item"/>
                        </li>
                    @endforeach
                @else
                    {{ $slot }}
                @endif
            </ul>
        </div>
    </div>
</div>

