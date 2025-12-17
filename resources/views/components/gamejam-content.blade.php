@php
    // $year, $jam, $games, $siteConfig are passed from the parent
@endphp

<!-- Page Header -->
<section class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl md:text-5xl font-bold mb-2">{{ $year }}</h1>
        @if($jam && isset($jam['topic']))
        <p class="text-xl text-indigo-200">{{ $jam['topic'] }}</p>
        @endif
    </div>
</section>

<!-- Filter Buttons -->
<section class="py-8 bg-gray-50 dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap justify-center gap-4" id="filter-buttons">
            <button class="filter-btn active px-6 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors" data-filter="*">All Games</button>
            <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-filter=".singleplayer">Singleplayer</button>
            <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-filter=".multiplayer">Multiplayer</button>
            <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-filter=".keyboard">Keyboard</button>
            <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-filter=".mouse">Mouse</button>
            <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-filter=".gamepad">Gamepad</button>
            <button class="filter-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-filter=".experimental">Experimental</button>
        </div>
    </div>
</section>

<!-- Games Grid -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="games-grid">
            @foreach($games as $entry)
            @php
                $game = $entry['game'] ?? [];
                $team = $entry['team'] ?? [];
                $gameName = $game['name'] ?? 'Unknown Game';
                $gameSlug = \Illuminate\Support\Str::slug($gameName ?? 'unknown');
                $players = $game['players'] ?? 1;
                $playerClass = $players == 1 ? 'singleplayer' : 'multiplayer';
                $controls = $game['controls'] ?? [];
                $controlClasses = implode(' ', array_map('strtolower', $controls));
                $description = $game['description'] ?? '';
                $headerImage = $entry['headerimage'] ?? '';
                $imagePath = $headerImage ? "{$year}/{$headerImage}" : '';
            @endphp
            <div class="game-card {{ $playerClass }} {{ $controlClasses }} bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden hover:shadow-xl transition-shadow">
                <div class="relative h-48 bg-gray-200 dark:bg-gray-700 overflow-hidden">
                    @if($imagePath && file_exists(base_path("_media/{$year}/{$headerImage}")))
                    <img src="/media/{{ $year }}/{{ $headerImage }}" alt="{{ $gameName }}" class="w-full h-full object-cover">
                    @else
                    <div class="w-full h-full flex items-center justify-center text-gray-400">
                        <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    @endif
                    <div class="absolute top-4 left-4">
                        <span class="bg-indigo-600 text-white px-3 py-1 rounded-full text-sm font-semibold">{{ $team['name'] ?? 'Unknown Team' }}</span>
                    </div>
                </div>
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-2 dark:text-white">
                        <a href="/{{ $year }}/{{ $gameSlug }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">{{ $gameName }}</a>
                    </h3>
                    <p class="text-gray-600 dark:text-gray-300 mb-4 line-clamp-3">{{ \Illuminate\Support\Str::limit(strip_tags($description), 100) }}</p>
                    <div class="flex items-center justify-between">
                        <div class="flex gap-2 flex-wrap">
                            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded">{{ $players }} Player{{ $players > 1 ? 's' : '' }}</span>
                            @foreach($controls as $control)
                            <span class="text-xs bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-2 py-1 rounded capitalize">{{ $control }}</span>
                            @endforeach
                        </div>
                        <a href="/{{ $year }}/{{ $gameSlug }}" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">Read More →</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn');
    const gameCards = document.querySelectorAll('.game-card');

    filterButtons.forEach(button => {
        button.addEventListener('click', function() {
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

