{{--
    A YouTube video that loads nothing from YouTube before the visitor has consented.
    Expected: $youtubeId (may be empty when set later by a script), $title.
--}}
<div class="youtube-embed" data-youtube-id="{{ $youtubeId }}">
    {{-- Shown when cookies were rejected or no choice was made --}}
    <div class="youtube-cookie-placeholder relative rounded-lg overflow-hidden shadow-2xl" style="padding-bottom: 56.25%; height: 0; position: relative;">
        <div class="absolute inset-0 flex flex-col items-center justify-center bg-gray-800 gap-4 p-8">
            <p class="text-gray-300 text-center text-lg">To watch the video, please accept YouTube cookies.</p>
            <button type="button" class="youtube-accept-cookies px-6 py-3 rounded-lg font-semibold bg-primary-600 text-white hover:bg-primary-700 transition-colors">
                Accept cookies
            </button>
        </div>
    </div>
    {{-- Shown when cookies were accepted --}}
    <div class="youtube-video-container relative rounded-lg overflow-hidden shadow-2xl hidden" style="padding-bottom: 56.25%; height: 0; position: relative;">
        <div class="youtube-placeholder absolute inset-0 flex items-center justify-center bg-gray-800 cursor-pointer group" role="button" tabindex="0" aria-label="Play {{ $title }}">
            <img alt="" class="youtube-thumbnail absolute inset-0 w-full h-full object-cover" loading="lazy" width="1280" height="720">
            <div class="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors"></div>
            <div class="relative z-10 w-20 h-20 flex items-center justify-center rounded-full bg-red-600 group-hover:bg-red-700 group-hover:scale-110 transition-all shadow-lg">
                <svg class="w-10 h-10 text-white ml-1" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
        </div>
        <div class="youtube-iframe-container absolute inset-0 hidden">
            <iframe class="absolute top-0 left-0 w-full h-full border-0"
                    title="{{ $title }}"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
        </div>
    </div>
</div>
