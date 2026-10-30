@extends('layouts.app')

@section('content')
@php
    // Resolve the project entry from the provided list + slug.
    $projectEntry = null;
    foreach (($projects ?? []) as $entry) {
        $project = $entry['project'] ?? [];
        $projectName = $project['name'] ?? '';
        if (\Illuminate\Support\Str::slug($projectName) === ($projectSlug ?? '')) {
            $projectEntry = $entry;
            break;
        }
    }

    if ($projectEntry) {
        $project = $projectEntry['project'] ?? [];
        $team = $projectEntry['team'] ?? [];
        $projectName = $project['name'] ?? 'Unknown ' . config('jam.labels.project');
        $description = $project['description'] ?? '';
        $players = $project['players'] ?? 1;
        $controls = $project['controls'] ?? [];
        $controlsText = $project['controls_text'] ?? null;
        $videos = $project['videos'] ?? [];
        $images = $projectEntry['images'] ?? [];
        $downloads = $projectEntry['download'] ?? [];
        $headerImage = $projectEntry['headerimage'] ?? '';
    }
@endphp

@if(!$projectEntry)
    <section class="py-16 bg-white dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <a href="/{{ $year }}" class="text-primary-600 dark:text-primary-400 font-semibold hover:underline">← Back to {{ $year }}</a>
            <h1 class="text-3xl font-bold mt-6 dark:text-white">{{ config('jam.labels.project') }} not found</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">The data for this page is missing, or the slug no longer matches.</p>
        </div>
    </section>
@else
    @include('components.project-detail-content', [
        'year' => $year,
        'jam' => $jam,
        'projectSlug' => $projectSlug,
        'projectName' => $projectName,
        'description' => $description,
        'players' => $players,
        'controls' => $controls,
        'controlsText' => $controlsText,
        'videos' => $videos,
        'images' => $images,
        'downloads' => $downloads,
        'headerImage' => $headerImage,
        'team' => $team ?? [],
    ])
@endif
@endsection


