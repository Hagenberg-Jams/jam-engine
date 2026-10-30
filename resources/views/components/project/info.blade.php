<!-- Info Boxes -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-12">
            <div class="bg-gray-200 dark:bg-gray-800 rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Players</h2>
                <p class="text-xl font-bold dark:text-white">
                    @php
                        // Format players display: support ranges like "3-8" or single numbers
                        if (preg_match('/^(\d+)-(\d+)$/', (string) $players, $matches)) {
                            echo $matches[1] . '–' . $matches[2] . ' Players';
                        } else {
                            $playerCount = (int) $players;
                            echo $playerCount . ' Player' . ($playerCount !== 1 ? 's' : '');
                        }
                    @endphp
                </p>
            </div>
            <div class="bg-gray-200 dark:bg-gray-800 rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Input</h2>
                <p class="text-xl font-bold dark:text-white">{{ implode(', ', array_map('ucfirst', $controls)) }}</p>
            </div>
            <div class="bg-gray-200 dark:bg-gray-800 rounded-lg p-6">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-2">Versions</h2>
                <p class="text-xl font-bold dark:text-white">
                    @php
                        $platforms = collect($downloads)->pluck('platform')->unique()->values();
                    @endphp
                    {{ $platforms->implode(', ') }}
                </p>
            </div>
        </div>
