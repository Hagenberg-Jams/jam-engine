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
                            $base = config('jam.downloads.base_url');
                            $downloadPath = config('jam.downloads.path', 'games');
                            $downloadUrl = $isUrl ? $file : ($base ? rtrim($base, '/') . '/' . $downloadPath . '/' . $year . '/' . $file : "/{$downloadPath}/{$year}/{$file}");
                        @endphp
                        <a href="{{ $downloadUrl }}"
                           class="bg-primary-600 hover:bg-primary-700 text-white px-6 py-4 rounded-lg text-center font-semibold transition-colors"
                           @if(!$isUrl) download @endif>
                            {{ $platform }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
