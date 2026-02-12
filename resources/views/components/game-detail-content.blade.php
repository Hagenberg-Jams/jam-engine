@php
    // Expected variables:
    // $year, $jam, $gameSlug, $gameName, $description, $players, $controls, $controlsText, $images, $downloads, $headerImage, $team
@endphp

<!-- Page Header -->
<section class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="mb-4">
            <a href="/{{ $year }}" class="text-indigo-200 hover:text-white transition-colors">← Back to {{ $year }}</a>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-2">{{ $gameName }}</h1>
        @if(isset($team['name']))
            <p class="text-xl text-indigo-200">{{ $team['name'] }}</p>
        @endif
    </div>
</section>

<!-- Game Details -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
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

        <!-- Header Image -->
        @if(!empty($headerImage) && file_exists(base_path("_media/{$year}/{$headerImage}")))
            <div class="mb-12 rounded-lg overflow-hidden shadow-xl">
                <img src="/media/{{ $year }}/{{ $headerImage }}" alt="{{ $gameName }}" class="w-full h-auto">
            </div>
        @endif

        <!-- Description -->
        <div class="prose prose-lg dark:prose-invert max-w-none mb-12">
            <h2 class="text-3xl font-bold mb-6 dark:text-white">About the Game</h2>
            <div class="text-gray-700 dark:text-gray-300">
                @php
                    // Split by double line breaks (paragraphs)
                    $paragraphs = preg_split('/\n\s*\n/', trim($description));
                @endphp
                @foreach($paragraphs as $paragraph)
                    @php
                        // Convert single line breaks to markdown hard breaks (two spaces + newline)
                        // This allows markdown to convert them to <br> while also processing links
                        $paragraphWithHardBreaks = preg_replace('/([^\n])\n([^\n])/', '$1  \n$2', trim($paragraph));
                        // Process markdown (converts links and hard breaks)
                        $htmlContent = \Illuminate\Support\Str::markdown($paragraphWithHardBreaks);
                        // Remove wrapping <p> tag if markdown added one (we add our own)
                        $htmlContent = preg_replace('/^<p>(.*)<\/p>$/s', '$1', $htmlContent);
                    @endphp
                    <p>{!! $htmlContent !!}</p>
                @endforeach
            </div>
        </div>

        <!-- Controls -->
        @if(!empty($controlsText))
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 dark:text-white">Controls</h2>
                <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6">
                    <pre class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans">{{ $controlsText }}</pre>
                </div>
            </div>
        @endif

        <!-- Team Members -->
        @if(isset($team['members']) && count($team['members']) > 0)
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 dark:text-white">Team Members</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($team['members'] as $member)
                        @php
                            $personSlug = \Illuminate\Support\Str::slug(trim(preg_replace('/\s+/', ' ', $member)));
                        @endphp
                        <div class="bg-gray-200 dark:bg-gray-800 rounded-lg p-4 text-center">
                            <a href="/person/{{ $personSlug }}" 
                               class="font-semibold dark:text-white hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors">
                                {{ $member }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Gallery -->
        @if(count($images) > 0)
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 dark:text-white">Screenshots</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($images as $image)
                        @php
                            $imageFile = $image['file'] ?? '';
                            $imageThumb = $image['thumb'] ?? '';
                        @endphp

                        @if($imageFile)
                            <div class="group relative overflow-hidden rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                                <a href="/media/{{ $year }}/{{ $imageFile }}" 
                                   data-lightbox="gallery-{{ $gameSlug }}" 
                                   data-title="{{ $gameName }} by {{ $team['name'] ?? 'Unknown Team' }}">
                                    <img src="/media/{{ $year }}/{{ $imageThumb ?: $imageFile }}" 
                                         alt="{{ $gameName }} Screenshot" 
                                         class="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300">
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Downloads -->
        @if(count($downloads) > 0)
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 dark:text-white">Download</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($downloads as $download)
                        @php
                            $file = $download['file'] ?? '';
                            $platform = $download['platform'] ?? 'Download';
                            $isUrl = str_starts_with($file, 'http://') || str_starts_with($file, 'https://');
                            $base = config('gamejam.games_base_url');
                            $downloadUrl = $isUrl ? $file : ($base ? rtrim($base, '/') . '/games/' . $year . '/' . $file : "/games/{$year}/{$file}");
                        @endphp
                        <a href="{{ $downloadUrl }}"
                           class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-4 rounded-lg text-center font-semibold transition-colors"
                           @if(!$isUrl) download @endif>
                            {{ $platform }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/css/glightbox.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/glightbox@3.2.0/dist/js/glightbox.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const lightbox = GLightbox({
                selector: '[data-lightbox="gallery-{{ $gameSlug }}"]',
                touchNavigation: true,
                loop: true,
                autoplayVideos: false,
            });
        });
    </script>
@endpush


