@extends('hyde::layouts.app')

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
        $images = $gameEntry['images'] ?? [];
        $downloads = $gameEntry['download'] ?? [];
        $headerImage = $gameEntry['headerimage'] ?? '';
    }
@endphp

@if(!$gameEntry)
    <section class="py-16 bg-white dark:bg-gray-900">
        <div class="container mx-auto px-4">
            <a href="/{{ $year }}" class="text-indigo-600 dark:text-indigo-400 font-semibold hover:underline">← Zurück zu {{ $year }}</a>
            <h1 class="text-3xl font-bold mt-6 dark:text-white">Game nicht gefunden</h1>
            <p class="mt-2 text-gray-600 dark:text-gray-300">Die Daten für diese Seite fehlen oder der Slug stimmt nicht mehr.</p>
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
        'images' => $images,
        'downloads' => $downloads,
        'headerImage' => $headerImage,
        'team' => $team ?? [],
    ])
@endif
@endsection


