@extends('layouts.app')

@section('content')
@php
    $personName = $personName ?? 'Unknown Person';
    $games = $games ?? [];
    $totalGames = $totalGames ?? count($games);
    $years = $years ?? [];
@endphp

<!-- Page Header -->
<section class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="mb-4">
            <a href="/people" class="text-indigo-200 hover:text-white transition-colors">← Back to People</a>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-2">{{ $personName }}</h1>
        <p class="text-xl text-indigo-200">
            {{ $totalGames }} Game{{ $totalGames !== 1 ? 's' : '' }} 
            @if(count($years) > 0)
                in {{ count($years) }} Game Jam{{ count($years) !== 1 ? 's' : '' }}
            @endif
        </p>
    </div>
</section>

<!-- Games List -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        @if(empty($games))
            <div class="text-center py-12">
                <p class="text-gray-600 dark:text-gray-400 text-lg">No games found for this person.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
                @foreach($games as $game)
                    <div class="bg-gray-200 dark:bg-gray-800 rounded-lg p-6 hover:shadow-lg transition-shadow">
                        <div class="mb-4">
                            <a href="/{{ $game['year'] }}/{{ $game['gameSlug'] }}" 
                               class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ $game['gameName'] }}
                            </a>
                        </div>
                        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <div>
                                <span class="font-semibold">Year:</span>
                                <a href="/{{ $game['year'] }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                    {{ $game['year'] }}
                                </a>
                            </div>
                            <div>
                                <span class="font-semibold">Team:</span>
                                <span class="ml-1">{{ $game['teamName'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection

