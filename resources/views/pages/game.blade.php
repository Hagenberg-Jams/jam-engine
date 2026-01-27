@extends('layouts.app')

@section('content')
@php
    // Resolve the game entry from the provided list + slug.
    $gameEntry = null;
    foreach (($games ?? []) as $entry) {
        $game = $entry['game'] ?? [];
        $gameName = $game['name'] ?? '';
        if (\Illuminate\Support\Str::slug($gameName) === ($gameSlug ?? '')) {
            $gameEntry = $entry;
            break;
        }
    }

    if ($gameEntry) {
        $game = $gameEntry['game'] ?? [];
        $team = $gameEntry['team'] ?? [];
        $gameName = $game['name'] ?? 'Unknown Game';
        $description = $game['description'] ?? '';
        $players = $game['players'] ?? 1;
        $controls = $game['controls'] ?? [];
        $controlsText = $game['controls_text'] ?? null;
        $images = $gameEntry['images'] ?? [];
        $downloads = $gameEntry['download'] ?? [];
        $headerImage = $gameEntry['headerimage'] ?? '';
    }
@endphp

@if(!$gameEntry)
    <section class="py-16 bg-white dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <a href="/{{ $year }}" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">← Back to {{ $year }}</a>
            <h1 class="text-3xl font-bold mt-6 dark:text-white">Game not found</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">The data for this page is missing, or the slug no longer matches.</p>
        </div>
    </section>
@else
    @include('components.game-detail-content', [
        'year' => $year,
        'jam' => $jam,
        'gameSlug' => $gameSlug,
        'gameName' => $gameName,
        'description' => $description,
        'players' => $players,
        'controls' => $controls,
        'controlsText' => $controlsText,
        'images' => $images,
        'downloads' => $downloads,
        'headerImage' => $headerImage,
        'team' => $team ?? [],
    ])
@endif
@endsection


