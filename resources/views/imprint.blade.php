@extends('layouts.app')

@section('content')
@php
    $imprint = \HagenbergJams\JamEngine\JamData::getImprint();
    $legal = $imprint['legal'] ?? [];
    $responsible = $imprint['responsible'] ?? [];
    $supportEmail = $imprint['support_email'] ?? config('jam.email');
    $linkClass = 'text-primary-600 dark:text-primary-400 hover:underline';
@endphp
<div class="container mx-auto px-4 py-16 max-w-4xl">
    <h1 class="text-4xl font-bold mb-8 dark:text-white">Imprint</h1>

    <div class="prose dark:prose-invert max-w-none">
        <h2>Legal Information</h2>
        <p>
            <strong>{{ $legal['name'] ?? '' }}</strong><br>
            @foreach($legal['address'] ?? [] as $line)
                {{ $line }}<br>
            @endforeach
            @if(!empty($legal['phone']))
                Phone: {{ $legal['phone'] }}<br>
            @endif
            @if(!empty($legal['email']))
                Email: {{ $legal['email'] }}<br>
            @endif
            @if(!empty($legal['website']))
                Website: <a href="{{ $legal['website'] }}" class="{{ $linkClass }}">{{ $legal['website_label'] ?? $legal['website'] }}</a>
            @endif
        </p>

        <h2>Content and Editorial Responsibility</h2>
        <p>
            <strong>{{ $responsible['name'] ?? '' }}</strong><br>
            @foreach($responsible['address'] ?? [] as $line)
                {{ $line }}<br>
            @endforeach
            Email: <a href="mailto:{{ $supportEmail }}" class="{{ $linkClass }}">{{ $supportEmail }}</a>
        </p>

        @if(!empty($imprint['picture_credits']))
            <h2>Picture Credits</h2>
            <p>
                @foreach($imprint['picture_credits'] as $credit)
                    {{ $credit }}@if(!$loop->last)<br>@endif
                @endforeach
            </p>
        @endif

        <h2>Technical Support</h2>
        <p>
            In the event of technical problems, please send an email to: <a href="mailto:{{ $supportEmail }}" class="{{ $linkClass }}">{{ $supportEmail }}</a>
        </p>

        @if(!empty($imprint['data_protection_url']))
            <h2>Data Protection</h2>
            <p>
                {{ $legal['prose_name'] ?? $legal['name'] ?? 'We' }} is committed to protecting your personal data.
                For detailed information about how we handle your data, please refer to our
                <a href="{{ $imprint['data_protection_url'] }}" class="{{ $linkClass }}" target="_blank">Data Protection Policy</a>.
            </p>
        @endif

        <h2 id="youtube">YouTube Embeds</h2>
        <p>
            This website embeds videos from YouTube. When you play a video, YouTube (operated by Google LLC, 1600 Amphitheatre Parkway, Mountain View, CA 94043, USA) may set cookies and collect data about your visit. This includes your IP address, the pages you visit, and interactions with the video. The data is processed according to Google's
            <a href="https://policies.google.com/privacy" class="{{ $linkClass }}" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and
            <a href="https://policies.google.com/terms" class="{{ $linkClass }}" target="_blank" rel="noopener noreferrer">Terms of Service</a>.
        </p>
        <p>
            We use YouTube's privacy-enhanced mode (youtube-nocookie.com), which delays cookie placement until you start playing a video. However, data may still be transferred to Google when you watch. We obtain your consent via a cookie banner before enabling video playback. If you reject cookies, the video on this site cannot be played.
        </p>

        <h2>Disclaimer</h2>
        <p>
            The information on this website has been carefully checked and is updated regularly.
            However, no guarantee can be given that all information is complete, correct and up to date at all times.
            {{ $legal['prose_name'] ?? $legal['name'] ?? 'The operator' }} assumes no liability for the content of external links.
        </p>
    </div>
</div>
@endsection
