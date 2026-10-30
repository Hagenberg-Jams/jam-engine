<!-- Gallery -->
        @if(count($images) > 0)
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 dark:text-white">{{ config('jam.labels.gallery') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($images as $image)
                        @php
                            $imageFile = $image['file'] ?? '';
                            $imageThumb = $image['thumb'] ?? '';
                        @endphp

                        @if($imageFile)
                            <div class="group relative overflow-hidden rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                                <a href="/media/{{ $year }}/{{ $imageFile }}" 
                                   data-lightbox="gallery-{{ $projectSlug }}" 
                                   data-title="{{ $projectName }} by {{ $team['name'] ?? 'Unknown Team' }}">
                                    <img src="/media/{{ $year }}/{{ $imageThumb ?: $imageFile }}" 
                                         alt="{{ $projectName }} {{ config('jam.labels.image') }}" 
                                         class="w-full h-64 object-cover group-hover:scale-105 transition-transform duration-300">
                                </a>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
