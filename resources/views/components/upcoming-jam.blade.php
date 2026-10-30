@php
    use HagenbergJams\JamEngine\JamSchedule;

    $upcoming = config('jam.features.upcoming_jam') ? JamSchedule::upcoming() : null;
    $topicJam = config('jam.features.topic_release') ? JamSchedule::currentTopicJam() : null;
    $registrationActive = (bool) config('jam.registration.active', false);
    $registrationUrl = (string) config('jam.registration.url', '');
    $registrationDeadline = JamSchedule::parse(config('jam.registration.deadline'));
@endphp

@if($upcoming)
    <section id="upcoming-jam" class="py-12 bg-primary-600 text-white" aria-labelledby="upcoming-jam-heading">
        <div class="container mx-auto px-4 text-center">
            <p class="uppercase tracking-wide text-sm text-primary-200 font-semibold">Next {{ config('jam.labels.jam') }}</p>
            <h2 id="upcoming-jam-heading" class="text-3xl md:text-4xl font-bold mt-2">{{ JamSchedule::formatDates($upcoming['jam']) }}</h2>
            @if($registrationActive && $registrationUrl)
                <a href="{{ $registrationUrl }}" target="_blank" class="inline-block mt-6 bg-white text-primary-700 hover:bg-primary-100 px-8 py-3 rounded-lg font-semibold transition-colors">
                    Register for {{ $upcoming['year'] }}
                </a>
                @if($registrationDeadline)
                    <p class="mt-3 text-primary-200 text-sm">Registration closes on {{ $registrationDeadline->format('j F Y, H:i') }}</p>
                @endif
            @endif
        </div>
    </section>
@endif

@if($topicJam)
    @include('components.topic-release', $topicJam)
@endif
