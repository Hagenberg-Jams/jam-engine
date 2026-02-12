@php
    use Hyde\Framework\Features\Navigation\NavigationItem;

    $latestJam = (string) config('gamejam.latest_jam', '2024');
    $archiveYears = array_values(array_filter(\App\GameJamData::getAvailableYears(), fn (int $y) => (string) $y !== $latestJam));
    rsort($archiveYears);
    
    // Group years by decade
    $decades = [];
    foreach ($archiveYears as $year) {
        // Determine decade: 2011-2019 = 2010s, 2020-2029 = 2020s, etc.
        $decade = (int) floor($year / 10) * 10;
        $decadeLabel = "{$decade}s";
        
        if (!isset($decades[$decadeLabel])) {
            $decades[$decadeLabel] = [];
        }
        $decades[$decadeLabel][] = $year;
    }
    
    // Sort decades descending (newest first)
    krsort($decades);
    
    // Sort years within each decade descending
    foreach ($decades as $decadeLabel => $years) {
        rsort($decades[$decadeLabel]);
    }
@endphp

<nav aria-label="Main navigation" id="main-navigation" class="flex flex-wrap items-center justify-between p-4 shadow-lg sm:shadow-xl md:shadow-none dark:bg-gray-800 bg-white">
    <div id="main-navigation-brand" class="flex grow items-center shrink-0 text-gray-700 dark:text-gray-200">
        @if(view()->exists('components.navigation-brand'))
            @include('components.navigation-brand')
        @else
            @include('hyde::components.navigation.navigation-brand')
        @endif
    </div>

    <div class="block md:hidden">
        <button id="navigation-toggle-button" type="button" class="flex items-center px-3 py-1 hover:text-gray-700 dark:text-gray-200"
                aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="main-navigation-links">
            <svg id="open-main-navigation-menu-icon" class="dark:fill-gray-200" xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" aria-hidden="true">
                <title>Open Menu</title>
                <path d="M0 0h24v24H0z" fill="none"/>
                <path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/>
            </svg>
            <svg id="close-main-navigation-menu-icon" class="dark:fill-gray-200 hidden" xmlns="http://www.w3.org/2000/svg" height="24" viewBox="0 0 24 24" width="24" aria-hidden="true">
                <title>Close Menu</title>
                <path d="M0 0h24v24H0z" fill="none"></path>
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"></path>
            </svg>
        </button>
    </div>

    <div id="main-navigation-links" 
         class="w-full hidden md:flex grow md:grow-0 md:items-center md:w-auto px-6 -mx-4 border-t mt-3 pt-3 md:border-none md:mt-0 md:py-0 border-gray-200 dark:border-gray-700">
        <ul aria-label="Navigation links" class="md:grow md:flex md:items-center justify-end">
            {{-- 1) Theme Toggler --}}
            <li class="md:mx-2">
                <x-hyde::navigation.theme-toggle-button/>
            </li>

            {{-- 2) Latest Jam --}}
            <li class="md:mx-2">
                <x-navigation.navigation-link :item="NavigationItem::create($latestJam, $latestJam, 10)"/>
            </li>

            {{-- 3) Archive Dropdowns (grouped by decade) --}}
            @if(count($archiveYears))
                @foreach($decades as $decadeLabel => $years)
                    <li class="md:mx-2">
                        @include('components.navigation.dropdown', [
                            'label' => $decadeLabel,
                            'items' => collect($years)->map(fn (int $y) => NavigationItem::create((string) $y, (string) $y, 900))
                        ])
                    </li>
                @endforeach
            @endif

            {{-- 4) People --}}
            <li class="md:mx-2">
                <x-navigation.navigation-link :item="NavigationItem::create('people', 'People', 15)"/>
            </li>

            {{-- 5) Rules --}}
            <li class="md:mx-2">
                <x-navigation.navigation-link :item="NavigationItem::create('rules', 'Rules', 20)"/>
            </li>

            {{-- 5) Imprint --}}
            <li class="md:mx-2">
                <x-navigation.navigation-link :item="NavigationItem::create('imprint', 'Imprint', 30)"/>
            </li>
        </ul>
    </div>
</nav>

