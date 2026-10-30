{{--
    Countdown to the topic release. Before the release, neither the topic nor the video is part of this page:
    the script fetches them from /api/topic, which only answers after the release time.
    Expected: $year, $jam, $release.
--}}
@php
    $pending = \HagenbergJams\JamEngine\JamSchedule::isTopicPending($jam);
    $topic = $pending ? null : ($jam['topic'] ?? null);
    $topicVideo = $pending ? null : ($jam['topic_video'] ?? null);
@endphp

<section id="topic-release" class="py-16 bg-gray-900 text-white" aria-labelledby="topic-release-heading"
         data-release="{{ $release->getTimestampMs() }}" data-endpoint="/api/topic" data-revealed="{{ $topic ? 'true' : 'false' }}">
    <div class="container mx-auto px-4 text-center">
        <h2 id="topic-release-heading" class="text-3xl md:text-4xl font-bold">Topic Reveal {{ $year }}</h2>

        <div id="topic-countdown" class="@if($topic) hidden @endif mt-8" role="timer" aria-live="off">
            <div class="flex flex-wrap justify-center gap-4">
                @foreach(['days' => 'Days', 'hours' => 'Hours', 'minutes' => 'Minutes', 'seconds' => 'Seconds'] as $unit => $label)
                    <div class="bg-gray-800 rounded-lg px-6 py-4 min-w-24">
                        <div class="text-4xl md:text-5xl font-bold tabular-nums" data-unit="{{ $unit }}">--</div>
                        <div class="uppercase tracking-wide text-xs text-gray-400 mt-1">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
            <p class="mt-6 text-gray-300">The topic will be revealed on {{ $release->format('j F Y \a\t H:i') }}.</p>
        </div>

        <div id="topic-revealed" class="@unless($topic) hidden @endunless mt-8" aria-live="polite">
            <p class="uppercase tracking-wide text-sm text-primary-400 font-semibold">The topic is</p>
            <p id="topic-text" class="text-4xl md:text-6xl font-bold mt-2">{{ $topic }}</p>
        </div>

        <div id="topic-video" class="@unless($topicVideo) hidden @endunless max-w-4xl mx-auto mt-8 text-left">
            @include('components.youtube-embed', ['youtubeId' => (string) $topicVideo, 'title' => "Topic Reveal {$year}"])
        </div>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var section = document.getElementById('topic-release');
    if (!section || section.getAttribute('data-revealed') === 'true') {
        return;
    }

    var release = Number(section.getAttribute('data-release'));
    var endpoint = section.getAttribute('data-endpoint');
    var countdown = document.getElementById('topic-countdown');
    var clockOffset = 0;
    var timer = null;
    var fetching = false;
    var retryDelay = 2000;

    function show(data) {
        clearInterval(timer);
        document.getElementById('topic-text').textContent = data.topic;
        countdown.classList.add('hidden');
        document.getElementById('topic-revealed').classList.remove('hidden');

        if (data.video) {
            var embed = document.querySelector('#topic-video .youtube-embed');
            embed.setAttribute('data-youtube-id', data.video);
            document.getElementById('topic-video').classList.remove('hidden');
            if (window.jamRefreshYoutubeEmbeds) {
                window.jamRefreshYoutubeEmbeds();
            }
        }
    }

    function askServer() {
        if (fetching) {
            return;
        }
        fetching = true;

        fetch(endpoint, { cache: 'no-store' })
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) {
                    if (typeof data.now === 'number') {
                        clockOffset = data.now - Date.now();
                    }
                    if (response.ok && data.topic) {
                        show(data);
                    } else if (Date.now() + clockOffset >= release) {
                        setTimeout(askServer, retryDelay);
                    } else if (timer === null) {
                        // The local clock ran ahead, so the countdown continues with the server time.
                        startCountdown();
                    }
                });
            })
            .catch(function () {
                if (Date.now() + clockOffset >= release) {
                    setTimeout(askServer, retryDelay);
                }
            })
            .finally(function () {
                fetching = false;
            });
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function tick() {
        var remaining = release - (Date.now() + clockOffset);

        if (remaining <= 0) {
            clearInterval(timer);
            timer = null;
            countdown.querySelectorAll('[data-unit]').forEach(function (element) {
                element.textContent = '00';
            });
            askServer();
            return;
        }

        var seconds = Math.floor(remaining / 1000);
        var values = {
            days: Math.floor(seconds / 86400),
            hours: Math.floor(seconds / 3600) % 24,
            minutes: Math.floor(seconds / 60) % 60,
            seconds: seconds % 60
        };

        Object.keys(values).forEach(function (unit) {
            countdown.querySelector('[data-unit="' + unit + '"]').textContent = pad(values[unit]);
        });
    }

    function startCountdown() {
        timer = setInterval(tick, 1000);
        tick();
    }

    startCountdown();
    // Corrects a wrong local clock, and reveals the topic if this page was built before the release.
    askServer();
});
</script>
@endpush
