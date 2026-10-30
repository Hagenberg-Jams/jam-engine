@if(count($videos) > 0)
    <div class="mb-12 space-y-8">
        @foreach($videos as $video)
            @if(!empty($video['youtube']))
                <div class="max-w-4xl mx-auto">
                    @if(!empty($video['title']))
                        <h2 class="text-3xl font-bold mb-6 dark:text-white">{{ $video['title'] }}</h2>
                    @endif
                    @include('components.youtube-embed', ['youtubeId' => $video['youtube'], 'title' => $video['title'] ?? $projectName])
                </div>
            @endif
        @endforeach
    </div>
@endif
