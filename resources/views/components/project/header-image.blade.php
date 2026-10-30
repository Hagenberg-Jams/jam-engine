<!-- Header Image -->
        @if(!empty($headerImage) && file_exists(base_path("_media/{$year}/{$headerImage}")))
            <div class="mb-12 rounded-lg overflow-hidden shadow-xl">
                <img src="/media/{{ $year }}/{{ $headerImage }}" alt="{{ $projectName }}" class="w-full h-auto">
            </div>
        @endif
