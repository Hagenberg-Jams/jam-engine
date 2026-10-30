@extends('layouts.app')

@section('content')
@php
    $personName = $personName ?? 'Unknown Person';
    $projects = $projects ?? [];
    $totalProjects = $totalProjects ?? count($projects);
    $years = $years ?? [];
@endphp

<!-- Page Header -->
<section class="bg-primary-600 text-white py-16">
    <div class="container mx-auto px-4">
        <div class="mb-4">
            <a href="/people" class="text-primary-200 hover:text-white transition-colors">← Back to People</a>
        </div>
        <h1 class="text-4xl md:text-5xl font-bold mb-2">{{ $personName }}</h1>
        <p class="text-xl text-primary-200">
            {{ $totalProjects }} {{ $totalProjects !== 1 ? config('jam.labels.projects') : config('jam.labels.project') }} 
            @if(count($years) > 0)
                in {{ count($years) }} {{ count($years) !== 1 ? config('jam.labels.jams') : config('jam.labels.jam') }}
                @php $activeRange = min($years) === max($years) ? (string) max($years) : min($years) . '–' . max($years); @endphp
                · Active: {{ $activeRange }}
            @endif
        </p>
    </div>
</section>

<!-- Projects List -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        @if(empty($projects))
            <div class="text-center py-12">
                <p class="text-gray-600 dark:text-gray-400 text-lg">No {{ config('jam.labels.projects_lower') }} found for this person.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-12">
                @foreach($projects as $project)
                    <div class="bg-gray-200 dark:bg-gray-800 rounded-lg p-6 hover:shadow-lg transition-shadow">
                        <div class="mb-4">
                            <a href="/{{ $project['year'] }}/{{ $project['projectSlug'] }}" 
                               class="text-2xl font-bold text-primary-600 dark:text-primary-400 hover:underline">
                                {{ $project['projectName'] }}
                            </a>
                        </div>
                        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <div>
                                <span class="font-semibold">Year:</span>
                                <a href="/{{ $project['year'] }}" class="hover:text-primary-600 dark:hover:text-primary-400">
                                    {{ $project['year'] }}
                                </a>
                            </div>
                            <div>
                                <span class="font-semibold">Team:</span>
                                <span class="ml-1">{{ $project['teamName'] }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection

