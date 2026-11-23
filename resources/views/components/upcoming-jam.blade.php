{{-- The next jam as a call to action in the hero of the home page: its dates, and the registration while it is open. --}}
@php
    use HagenbergJams\JamEngine\JamSchedule;

    $upcoming = config('jam.features.upcoming_jam') ? JamSchedule::upcoming() : null;
    $registrationActive = (bool) config('jam.registration.active', false);
    $registrationUrl = (string) config('jam.registration.url', '');
    $registrationForm = $registrationUrl === '' && \HagenbergJams\JamEngine\Registration::enabled();
    if ($registrationForm) {
        $registrationUrl = Hyde::relativeLink('register.html');
    }
    $registrationDeadline = JamSchedule::parse(config('jam.registration.deadline'));
    // After the deadline the button goes, with the next build at the latest. The form itself closes on time.
    if ($registrationDeadline !== null && \Carbon\CarbonImmutable::now()->greaterThan($registrationDeadline)) {
        $registrationActive = false;
    }
@endphp

@if($upcoming)
    <section id="upcoming-jam" aria-labelledby="upcoming-jam-heading"
             class="pointer-events-auto mx-auto mb-8 max-w-2xl rounded-2xl border border-white/20 bg-black/40 px-6 py-5 md:px-10 md:py-7 backdrop-blur-sm shadow-2xl">
        <p class="uppercase tracking-widest text-xs md:text-sm text-primary-200 font-semibold">Next {{ config('jam.labels.jam') }}</p>
        <h2 id="upcoming-jam-heading" class="text-2xl md:text-3xl font-bold mt-1 text-balance">{{ JamSchedule::formatDates($upcoming['jam']) }}</h2>
        @if($registrationActive && $registrationUrl)
            <a href="{{ $registrationUrl }}" @unless($registrationForm) target="_blank" @endunless
               class="inline-block mt-5 bg-white text-primary-700 hover:bg-primary-100 active:bg-primary-200 px-10 py-4 rounded-lg text-lg font-bold shadow-lg transition-colors">
                Register for {{ $upcoming['year'] }}
            </a>
            @if($registrationDeadline)
                <p class="mt-3 text-sm text-gray-200">Registration closes on {{ $registrationDeadline->format('j F Y, H:i') }}</p>
            @endif
        @endif
    </section>
@endif
