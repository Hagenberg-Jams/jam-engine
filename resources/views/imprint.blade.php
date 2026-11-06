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

        @if(\HagenbergJams\JamEngine\Registration::enabled())
            <h2 id="registration">Registration</h2>
            <p>
                @php
                    $retention = (int) config('jam.registration.form.retention_days', 90);
                    $registrationFields = config('jam.registration.form.fields');
                    $registrationData = array_filter([
                        'the team name',
                        'a contact e-mail address',
                        'the real name of every person',
                        'optionally a nickname and the choice which of the two names appears on the website',
                        $registrationFields['discord'] ? 'Discord usernames' : null,
                        $registrationFields['programs'] ? 'the study programme of every person' : null,
                        $registrationFields['participation'] ? 'how the team takes part' : null,
                        $registrationFields['comment'] ? 'your comment' : null,
                    ]);
                @endphp
                When you register for a jam on this website, we process the data you enter: {{ implode(', ', array_slice($registrationData, 0, -1)) }} and {{ end($registrationData) }}.
                We use the data to organise the jam: to confirm registrations, to assign seats on site, to contact you, and to give the security staff on site a list of the participants.
                After the jam, the team name and the name each person chose appear on this website, for example with the project. Real names are only shown for people who chose them.
            </p>
            <p>
                The data is stored with Cloudflare, Inc. (101 Townsend St, San Francisco, CA 94107, USA) in a Cloudflare D1 database, and the mails about your registration are sent through the Cloudflare Email Service.
                Cloudflare processes the data on our behalf. Transfers to the USA are based on the EU-U.S. Data Privacy Framework and the standard contractual clauses of the European Commission, see the
                <a href="https://www.cloudflare.com/cloudflare-customer-dpa/" class="{{ $linkClass }}" target="_blank" rel="noopener noreferrer">Cloudflare Data Processing Addendum</a>.
                To protect the form against spam, it uses Cloudflare Turnstile, which checks your browser without cookies, see the
                <a href="https://www.cloudflare.com/privacypolicy/" class="{{ $linkClass }}" target="_blank" rel="noopener noreferrer">Cloudflare Privacy Policy</a>.
            </p>
            <p>
                Only the organisers can see the registrations, after signing in. You can change or cancel your registration at any time with the link in your confirmation mail.
                All registration data is deleted automatically {{ $retention }} days after the end of the jam. The legal basis is your consent and the organisation of the event you registered for (Art. 6 (1) (a) and (b) GDPR).
                You have the right to access, rectification, erasure, restriction of processing, data portability and objection, and you can lodge a complaint with the data protection authority. Write to
                <a href="mailto:{{ config('jam.email') }}" class="{{ $linkClass }}">{{ config('jam.email') }}</a> for any of these.
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
