@php
    /** @var string $label */
    /** @var \Illuminate\Support\Collection|\Hyde\Framework\Features\Navigation\NavigationMenu|null $items */
    $routeKey = \Hyde\Foundation\Facades\Routes::has($label) ? $label : null;
@endphp

<div class="dropdown-container">
    {{-- Mobile: details/summary list --}}
    <div class="md:hidden">
        <details>
            <summary class="group flex items-center gap-1 w-full my-2 py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100 cursor-pointer list-none [&::-webkit-details-marker]:hidden"
                    aria-haspopup="true">
                <span>{{ $label }}</span>
                <svg class="dropdown-chevron inline transition-transform duration-200 ease-in-out dark:fill-white" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000" aria-hidden="true">
                    <path d="M0 0h24v24H0z" fill="none"/>
                    <path d="M7 10l5 5 5-5z"/>
                </svg>
            </summary>
            <ul class="ml-4 space-y-1 mt-1">
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
        </details>
    </div>

    {{-- Desktop: details with absolute dropdown --}}
    <div class="hidden md:block relative">
        <details class="relative">
            <summary class="group flex items-center gap-1 cursor-pointer list-none [&::-webkit-details-marker]:hidden"
                    aria-haspopup="true">
                <span class="block my-2 md:my-0 md:inline-block py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100">{{ $label }}</span>
                <button type="button" class="dropdown-button inline-flex cursor-pointer items-center py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100" aria-label="{{ $label }} – expand to view years"
                        onclick="var d=this.closest('details'); d.toggleAttribute('open'); event.preventDefault(); event.stopPropagation();">
                    <svg class="dropdown-chevron inline transition-transform duration-200 ease-in-out dark:fill-white" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24" width="24px" fill="#000000" aria-hidden="true">
                        <path d="M0 0h24v24H0z" fill="none"/>
                        <path d="M7 10l5 5 5-5z"/>
                    </svg>
                </button>
            </summary>
            <div class="dropdown absolute shadow-lg bg-white dark:bg-gray-700 z-50 right-0 mt-1">
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
        </details>
    </div>
</div>
