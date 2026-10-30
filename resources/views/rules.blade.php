@extends('layouts.app')

@section('content')
@php
    $rules = \HagenbergJams\JamEngine\JamData::getRules();
    $columnSize = ceil(count($rules) / 2);
@endphp

<!-- Page Header -->
<section class="bg-primary-600 text-white py-16">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl md:text-5xl font-bold">Rules</h1>
    </div>
</section>

<!-- Rules Section -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left Column -->
            <div>
                <div class="space-y-4">
                    @foreach(array_slice($rules, 0, $columnSize) as $index => $rule)
                    <details class="group border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <summary class="list-none cursor-pointer px-6 py-4 flex items-center justify-between bg-gray-200 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors [&::-webkit-details-marker]:hidden">
                            <span class="font-semibold text-lg dark:text-white">{{ $rule['question'] ?? '' }}</span>
                            <svg class="w-5 h-5 transform transition-transform group-open:rotate-180 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </summary>
                        <div class="px-6 py-4 prose dark:prose-invert max-w-none border-t border-gray-200 dark:border-gray-700">
                            {!! $rule['answer'] ?? '' !!}
                        </div>
                    </details>
                    @endforeach
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <div class="space-y-4">
                    @foreach(array_slice($rules, $columnSize) as $index => $rule)
                    <details class="group border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                        <summary class="list-none cursor-pointer px-6 py-4 flex items-center justify-between bg-gray-200 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors [&::-webkit-details-marker]:hidden">
                            <span class="font-semibold text-lg dark:text-white">{{ $rule['question'] ?? '' }}</span>
                            <svg class="w-5 h-5 transform transition-transform group-open:rotate-180 flex-shrink-0 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </summary>
                        <div class="px-6 py-4 prose dark:prose-invert max-w-none border-t border-gray-200 dark:border-gray-700">
                            {!! $rule['answer'] ?? '' !!}
                        </div>
                    </details>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

