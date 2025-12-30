@extends('layouts.app')

@section('content')
@php
    $persons = $persons ?? [];
@endphp

<!-- Page Header -->
<section class="bg-indigo-600 text-white py-16">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl md:text-5xl font-bold mb-2">People of the Hagenberg Game Jam</h1>
        <p class="text-xl text-indigo-200">
            {{ count($persons) }} Participant{{ count($persons) !== 1 ? 's' : '' }}
        </p>
    </div>
</section>

<!-- Sort Buttons -->
<section class="py-8 bg-gray-50 dark:bg-gray-800">
    <div class="container mx-auto px-4">
        <div class="flex flex-wrap items-center justify-center gap-4">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                </svg>
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Sort by:</span>
            </div>
            <div class="flex flex-wrap justify-center gap-4" id="sort-buttons">
                <button class="sort-btn active px-6 py-2 bg-indigo-600 text-white rounded-lg font-semibold hover:bg-indigo-700 transition-colors" data-sort="name-asc">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"></path>
                        </svg>
                        Name (A-Z)
                    </span>
                </button>
                <button class="sort-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-sort="name-desc">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h9m5-4v12m0 0l-4-4m4 4l4-4"></path>
                        </svg>
                        Name (Z-A)
                    </span>
                </button>
                <button class="sort-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-sort="jams-desc">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                        Most Jams
                    </span>
                </button>
                <button class="sort-btn px-6 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-lg font-semibold hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors" data-sort="jams-asc">
                    <span class="flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path>
                        </svg>
                        Fewest Jams
                    </span>
                </button>
            </div>
        </div>
    </div>
</section>

<!-- People List -->
<section class="py-16 bg-white dark:bg-gray-900">
    <div class="container mx-auto px-4">
        @if(empty($persons))
            <div class="text-center py-12">
                <p class="text-gray-600 dark:text-gray-400 text-lg">No participants found.</p>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4" id="people-grid">
                @foreach($persons as $person)
                    <div class="person-card bg-gray-50 dark:bg-gray-800 rounded-lg p-6 hover:shadow-lg transition-shadow text-center" 
                         data-name="{{ strtolower($person['name']) }}" 
                         data-jams="{{ count($person['years']) }}">
                        <a href="/person/{{ $person['slug'] }}" 
                           class="text-xl font-bold text-indigo-600 dark:text-indigo-400 hover:underline block mb-2">
                            {{ $person['name'] }}
                        </a>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ $person['totalGames'] }} Game{{ $person['totalGames'] !== 1 ? 's' : '' }}
                            @if(count($person['years']) > 0)
                                · {{ count($person['years']) }} Jam{{ count($person['years']) !== 1 ? 's' : '' }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sortButtons = document.querySelectorAll('.sort-btn');
    const peopleGrid = document.getElementById('people-grid');
    const personCards = Array.from(document.querySelectorAll('.person-card'));

    if (!peopleGrid || personCards.length === 0) {
        return;
    }

    function sortPeople(sortType) {
        const sorted = [...personCards];

        switch(sortType) {
            case 'name-asc':
                sorted.sort((a, b) => {
                    const nameA = a.getAttribute('data-name');
                    const nameB = b.getAttribute('data-name');
                    return nameA.localeCompare(nameB);
                });
                break;
            case 'name-desc':
                sorted.sort((a, b) => {
                    const nameA = a.getAttribute('data-name');
                    const nameB = b.getAttribute('data-name');
                    return nameB.localeCompare(nameA);
                });
                break;
            case 'jams-desc':
                sorted.sort((a, b) => {
                    const jamsA = parseInt(a.getAttribute('data-jams')) || 0;
                    const jamsB = parseInt(b.getAttribute('data-jams')) || 0;
                    if (jamsB !== jamsA) {
                        return jamsB - jamsA;
                    }
                    // If same number of jams, sort alphabetically
                    return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                });
                break;
            case 'jams-asc':
                sorted.sort((a, b) => {
                    const jamsA = parseInt(a.getAttribute('data-jams')) || 0;
                    const jamsB = parseInt(b.getAttribute('data-jams')) || 0;
                    if (jamsA !== jamsB) {
                        return jamsA - jamsB;
                    }
                    // If same number of jams, sort alphabetically
                    return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
                });
                break;
        }

        // Animate and reorder
        personCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
        });

        setTimeout(() => {
            sorted.forEach(card => {
                peopleGrid.appendChild(card);
            });

            setTimeout(() => {
                personCards.forEach(card => {
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                });
            }, 50);
        }, 300);
    }

    sortButtons.forEach(button => {
        button.addEventListener('click', function() {
            sortButtons.forEach(btn => {
                btn.classList.remove('active', 'bg-indigo-600', 'text-white');
                btn.classList.add('bg-gray-200', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200');
            });
            this.classList.add('active', 'bg-indigo-600', 'text-white');
            this.classList.remove('bg-gray-200', 'dark:bg-gray-700', 'text-gray-800', 'dark:text-gray-200');

            const sortType = this.getAttribute('data-sort');
            sortPeople(sortType);
        });
    });

    // Initialize transitions
    personCards.forEach(card => {
        card.style.transition = 'opacity 0.3s, transform 0.3s';
        card.style.opacity = '1';
        card.style.transform = 'scale(1)';
    });
});
</script>
@endpush
@endsection

