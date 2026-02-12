@php
    // $year, $jam, $games, $siteConfig are passed from the parent
    use Carbon\Carbon;
    use Illuminate\Support\Str;
@endphp

        <!-- Page Header -->
<section class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl md:text-5xl font-bold mb-2">{{ $year }}</h1>
        @if($jam && isset($jam['topic']))
            <p class="text-xl text-indigo-200 mb-4">{{ $jam['topic'] }}</p>
        @endif
        @if($jam && (isset($jam['startdate']) || isset($jam['enddate']) || isset($jam['hours'])))
            <p class="text-indigo-200">
                @if(isset($jam['startdate']) && isset($jam['enddate']))
                    @php
                        $startDate = Carbon::parse($jam['startdate']);
                        $endDate = Carbon::parse($jam['enddate']);
                    @endphp
                    {{ $startDate->format('jS F Y') }}&ndash;{{ $endDate->format('jS F Y') }}
                @elseif(isset($jam['startdate']))
                    @php
                        $startDate = Carbon::parse($jam['startdate']);
                    @endphp
                    {{ $startDate->format('jS F Y') }}
                @endif
                @if(isset($jam['hours']))
                    @if(isset($jam['startdate']) || isset($jam['enddate']))
                        |
                    @endif
                    {{ $jam['hours'] }} hours
                @endif
            </p>
        @endif
    </div>
</section>

<!-- Filter Buttons -->
<section class="py-8 bg-gray-200 dark:bg-gray-800" aria-labelledby="filter-heading">
    <h2 id="filter-heading" class="sr-only">Filter games</h2>
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap items-center justify-center gap-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor"
                     viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                </svg>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Filter:</span>
            </div>
            <div class="flex flex-wrap justify-center gap-4" id="filter-buttons">
                <button class="filter-btn active px-6 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors"
                        data-filter="*">All Games
                </button>
                <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        data-filter=".singleplayer">Singleplayer
                </button>
                <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        data-filter=".multiplayer">Multiplayer
                </button>
                <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        data-filter=".keyboard">Keyboard
                </button>
                <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        data-filter=".mouse">Mouse
                </button>
                <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        data-filter=".gamepad">Gamepad
                </button>
                <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
                        data-filter=".experimental">Experimental
                </button>
            </div>
        </div>
    </div>
</section>

<!-- Winners Section -->
@php
    $winners = collect($games)->filter(function($entry) {
        $winner = $entry['winner'] ?? 'no';
        return $winner !== 'no' && $winner !== '';
    });
    
    $winnersByCategory = [];
    $hasWinners = false;
    
    if($winners->isNotEmpty()) {
        foreach($winners as $entry) {
            $winner = $entry['winner'] ?? 'no';
            $gameName = $entry['game']['name'] ?? 'Unknown Game';
            $teamName = $entry['team']['name'] ?? 'Unknown Team';
            $gameSlug = Str::slug($gameName);
            
            // Handle multiple categories (e.g., "jury, people")
            $categories = array_map('trim', explode(',', $winner));
            
            foreach($categories as $category) {
                if(!isset($winnersByCategory[$category])) {
                    $winnersByCategory[$category] = [];
                }
                $winnersByCategory[$category][] = [
                    'game' => $gameName,
                    'team' => $teamName,
                    'slug' => $gameSlug,
                ];
            }
        }
        
        $hasWinners = count($winnersByCategory) > 0;
    }
    
    // Category display names
    $categoryNames = [
        'overall' => 'Overall Winner',
        'jury' => 'Jury Award',
        'people' => 'People\'s Choice',
        'risingstars' => 'Rising Stars',
        'masterminds' => 'Masterminds',
        'youngblood' => 'Young Blood',
    ];
    
    // Category order
    $categoryOrder = ['overall', 'jury', 'people', 'youngblood', 'risingstars', 'masterminds'];
@endphp

@if($hasWinners)
    <section class="py-12 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold mb-6 dark:text-white">Winners</h2>
            <div class="space-y-4">
                @foreach($categoryOrder as $category)
                    @if(isset($winnersByCategory[$category]))
                        @foreach($winnersByCategory[$category] as $winner)
                            <p class="text-gray-700 dark:text-gray-300">
                                Winner in the category <strong
                                        class="font-semibold dark:text-white">{{ $categoryNames[$category] ?? ucfirst($category) }}</strong>:
                                <a href="/{{ $year }}/{{ $winner['slug'] }}"
                                   class="text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">{{ $winner['game'] }}</a>
                                by {{ $winner['team'] }}
                            </p>
                        @endforeach
                    @endif
                @endforeach
            </div>
        </div>
    </section>
@endif

<!-- Games Grid -->
<section class="py-16 bg-white dark:bg-gray-900" aria-labelledby="games-heading">
    <h2 id="games-heading" class="sr-only">Games</h2>
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="games-grid">
            @foreach($games as $entry)
                @php
                    $game = $entry['game'] ?? [];
                    $team = $entry['team'] ?? [];
                    $gameName = $game['name'] ?? 'Unknown Game';
                    $gameSlug = Str::slug($gameName ?? 'unknown');
                    $players = $game['players'] ?? 1;
                    // Extract minimum player count for filtering (handle ranges like "3-8")
                    $minPlayers = 1;
                    if (preg_match('/^(\d+)-(\d+)$/', (string) $players, $matches)) {
                        $minPlayers = (int) $matches[1];
                    } else {
                        $minPlayers = (int) $players;
                    }
                    $playerClass = $minPlayers == 1 ? 'singleplayer' : 'multiplayer';
                    $controls = $game['controls'] ?? [];
                    $controlClasses = implode(' ', array_map('strtolower', $controls));
                    $description = $game['description'] ?? '';
                    $headerImage = $entry['headerimage'] ?? '';
                    $imagePath = $headerImage ? "{$year}/{$headerImage}" : '';
                    $winner = $entry['winner'] ?? 'no';
                    $isWinner = $winner !== 'no' && $winner !== '';
                @endphp
                <div class="game-card {{ $playerClass }} {{ $controlClasses }} bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                    <div class="relative h-48 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        @if($imagePath && file_exists(base_path("_media/{$year}/{$headerImage}")))
                            <img src="/media/{{ $year }}/{{ $headerImage }}" alt="{{ $gameName }}"
                                 class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-gray-400">
                                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif
                        <div class="absolute top-4 left-4">
                            <span class="bg-indigo-600 text-white px-3 py-1 rounded-full text-sm font-semibold">{{ $team['name'] ?? 'Unknown Team' }}</span>
                        </div>
                        @if($isWinner)
                            <div class="absolute top-4 right-4">
                        <span class="text-white px-3 py-1 rounded-full text-sm font-semibold flex items-center gap-1"
                              style="background-color: #fbbf24;">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 512.00099 512"
                                 xmlns="http://www.w3.org/2000/svg">
                                <path d="M 497 36.953125 L 431.296875 36.953125 C 431.535156 29.675781 431.667969 22.355469 431.667969 15 C 431.667969 6.714844 424.949219 0 416.667969 0 L 95.332031 0 C 87.050781 0 80.332031 6.714844 80.332031 15 C 80.332031 22.355469 80.464844 29.675781 80.703125 36.953125 L 15 36.953125 C 6.714844 36.953125 0 43.667969 0 51.953125 C 0 119.164062 17.566406 182.574219 49.460938 230.507812 C 80.988281 277.894531 122.984375 305.074219 168.351562 307.71875 C 178.636719 318.910156 189.507812 328.035156 200.832031 334.996094 L 200.832031 401.664062 L 175.667969 401.664062 C 145.246094 401.664062 120.5 426.414062 120.5 456.832031 L 120.5 481.996094 L 119.433594 481.996094 C 111.148438 481.996094 104.433594 488.714844 104.433594 496.996094 C 104.433594 505.28125 111.148438 511.996094 119.433594 511.996094 L 392.566406 511.996094 C 400.851562 511.996094 407.566406 505.28125 407.566406 496.996094 C 407.566406 488.714844 400.851562 481.996094 392.566406 481.996094 L 391.5 481.996094 L 391.5 456.832031 C 391.5 426.414062 366.753906 401.664062 336.332031 401.664062 L 311.167969 401.664062 L 311.167969 334.996094 C 322.492188 328.039062 333.367188 318.910156 343.652344 307.71875 C 389.015625 305.074219 431.011719 277.894531 462.542969 230.507812 C 494.4375 182.574219 512 119.164062 512 51.953125 C 512 43.667969 505.285156 36.953125 497 36.953125 Z M 74.4375 213.890625 C 48.128906 174.355469 32.671875 122.644531 30.316406 66.953125 L 82.378906 66.953125 C 87.789062 135.414062 103.859375 198.695312 128.976562 248.925781 C 132.976562 256.925781 137.160156 264.484375 141.5 271.601562 C 116.550781 262.179688 93.460938 242.484375 74.4375 213.890625 Z M 361.5 456.832031 L 361.5 482 L 150.5 482 L 150.5 456.832031 C 150.5 442.957031 161.789062 431.664062 175.667969 431.664062 L 336.332031 431.664062 C 350.210938 431.664062 361.5 442.957031 361.5 456.832031 Z M 281.167969 401.664062 L 230.832031 401.664062 L 230.832031 348.03125 C 239.078125 350.203125 247.480469 351.332031 256 351.332031 C 264.519531 351.332031 272.921875 350.203125 281.167969 348.03125 Z M 290.457031 312.34375 C 289.78125 312.621094 289.132812 312.957031 288.511719 313.328125 C 277.910156 318.601562 267.015625 321.332031 256 321.332031 C 244.988281 321.332031 234.097656 318.601562 223.5 313.335938 C 222.871094 312.957031 222.21875 312.621094 221.535156 312.335938 C 209.773438 306.117188 198.394531 296.726562 187.632812 284.386719 C 187.066406 283.578125 186.429688 282.832031 185.722656 282.152344 C 175.039062 269.46875 164.988281 253.867188 155.808594 235.507812 C 128.242188 180.378906 112.320312 107.890625 110.507812 30 L 401.492188 30 C 399.675781 107.890625 383.753906 180.382812 356.191406 235.507812 C 347.011719 253.867188 336.960938 269.46875 326.28125 282.152344 C 325.570312 282.832031 324.925781 283.582031 324.363281 284.390625 C 313.601562 296.734375 302.21875 306.121094 290.457031 312.34375 Z M 437.5625 213.890625 C 418.539062 242.484375 395.449219 262.179688 370.5 271.601562 C 374.839844 264.484375 379.023438 256.925781 383.023438 248.925781 C 408.140625 198.695312 424.207031 135.414062 429.621094 66.953125 L 481.683594 66.953125 C 479.328125 122.644531 463.871094 174.355469 437.5625 213.890625 Z"/>
                            </svg>
                        </span>
                            </div>
                        @endif
                    </div>
                    <div class="p-6">
                        <h3 class="text-xl font-bold mb-2 dark:text-white">
                            <a href="/{{ $year }}/{{ $gameSlug }}"
                               class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">{{ $gameName }}</a>
                        </h3>
                        <p class="text-gray-600 dark:text-gray-300 mb-4 line-clamp-3">{{ Str::limit(strip_tags($description), 100) }}</p>
                        <div class="flex gap-2 flex-wrap mb-4">
                        <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">
                            @php
                                // Format players display: support ranges like "3-8" or single numbers
                                if (preg_match('/^(\d+)-(\d+)$/', (string) $players, $matches)) {
                                    echo $matches[1] . '–' . $matches[2] . ' Players';
                                } else {
                                    $playerCount = (int) $players;
                                    echo $playerCount . ' Player' . ($playerCount !== 1 ? 's' : '');
                                }
                            @endphp
                        </span>
                            @foreach($controls as $control)
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded capitalize">{{ $control }}</span>
                            @endforeach
                            @php
                                $downloads = $entry['download'] ?? [];
                                $platforms = collect($downloads)->pluck('platform')->unique()->values();
                            @endphp
                            @foreach($platforms as $platform)
                                <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">{{ $platform }}</span>
                            @endforeach
                        </div>
                        <div>
                            <a href="/{{ $year }}/{{ $gameSlug }}"
                               class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Details and
                                Download →</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const gameCards = document.querySelectorAll('.game-card');

            filterButtons.forEach(button => {
                button.addEventListener('click', function () {
                    filterButtons.forEach(btn => {
                        btn.classList.remove('active', 'bg-indigo-600', 'text-white');
                        btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200');
                    });
                    this.classList.add('active', 'bg-indigo-600', 'text-white');
                    this.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200');

                    const filter = this.getAttribute('data-filter');

                    gameCards.forEach(card => {
                        if (filter === '*' || card.matches(filter)) {
                            card.style.display = 'block';
                            setTimeout(() => {
                                card.style.opacity = '1';
                                card.style.transform = 'scale(1)';
                            }, 10);
                        } else {
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                card.style.display = 'none';
                            }, 300);
                        }
                    });
                });
            });

            gameCards.forEach(card => {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            });
        });
    </script>
@endpush

