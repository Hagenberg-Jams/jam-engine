@php
    // Expected variables:
    // $year, $jam, $projectSlug, $projectName, $description, $players, $controls, $controlsText, $videos, $images, $downloads, $headerImage, $team
@endphp

<!-- Page Header -->
<section class="bg-primary-600 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="mb-4">
            <a href="/{{ $year }}" class="text-primary-200 hover:text-white transition-colors">← Back to {{ $year }}</a>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-2">{{ $projectName }}</h1>
        @if(isset($team['name']))
            <p class="text-xl text-primary-200">{{ $team['name'] }}</p>
        @endif
    </div>
</section>

<!-- Project Details -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        @foreach(config('jam.project.sections', []) as $section)
            @include('components.project.' . $section)
        @endforeach
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
                selector: '[data-lightbox="gallery-{{ $projectSlug }}"]',
                touchNavigation: true,
                loop: true,
                autoplayVideos: false,
            });
        });
    </script>
@endpush


