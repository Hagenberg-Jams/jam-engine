@extends('layouts.app')

@push('meta')
<meta name="robots" content="{{ $mode === 'edit' ? 'noindex, nofollow' : 'index, follow' }}">
@endpush

@section('content')
@php
    use HagenbergJams\JamEngine\Registration;

    $form = config('jam.registration.form');
    $fields = $form['fields'];
    [$minSize, $maxSize] = array_values($form['team_size']);
    $deadline = Registration::deadline();
    $open = Registration::isOpen();
    $edit = $mode === 'edit';
    $input = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500';
    $label = 'block font-semibold mb-1';
    $hint = 'text-sm text-gray-600 dark:text-gray-400 mt-1';
    $wishes = [
        'onsite_only' => 'Our whole team only takes part on site. If there is no seat for us, we do not take part.',
        'onsite_preferred' => 'Our whole team prefers to be on site, but takes part remotely if it is full.',
        'remote' => 'Our whole team takes part remotely.',
    ];
@endphp

<section class="bg-primary-600 text-white py-16">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl md:text-5xl font-bold mb-2">{{ $edit ? 'Your Registration' : 'Register' }} for the {{ config('hyde.name') }} {{ $year }}</h1>
        @if($deadline)
            <p class="text-xl text-primary-200">Registration closes on {{ $deadline->format('j F Y, H:i') }}</p>
        @endif
    </div>
</section>

<section class="py-12">
    <div class="container mx-auto px-4 max-w-3xl">
        <div id="registration-closed" class="{{ $open ? 'hidden' : '' }} rounded-lg bg-gray-200 dark:bg-gray-800 p-6 text-lg">
            Registration is closed. If you have questions, write to <a class="text-primary-600 dark:text-primary-400 underline" href="mailto:{{ config('jam.email') }}">{{ config('jam.email') }}</a>.
        </div>

        <div id="registration-message" class="hidden rounded-lg p-6 text-lg mb-8" role="status" aria-live="polite"></div>

        @if(!$edit && !empty($form['texts']['intro']))
            <div class="prose dark:prose-invert max-w-none mb-8">{!! \Illuminate\Support\Str::markdown((string) $form['texts']['intro']) !!}</div>
        @endif

        <form id="registration-form" class="{{ $open || $edit ? '' : 'hidden' }} space-y-8" novalidate
              data-mode="{{ $mode }}" data-min="{{ $minSize }}" data-max="{{ $maxSize }}">
            <fieldset class="space-y-4">
                <legend class="text-2xl font-bold mb-2">Team</legend>
                <div>
                    <label for="team_name" class="{{ $label }}">Team name</label>
                    <input id="team_name" name="team_name" maxlength="80" required class="{{ $input }}">
                    <p class="{{ $hint }}">We love creative names! It appears on the website with your project.</p>
                </div>
                <div>
                    <label for="contact_email" class="{{ $label }}">Contact e-mail</label>
                    <input id="contact_email" name="contact_email" type="email" maxlength="200" required class="{{ $input }}" autocomplete="email">
                    <p class="{{ $hint }}">We send the confirmation and the link to change your registration to this address. It is never shown on the website.</p>
                </div>
                @if($fields['programs'] && !empty($form['programs']))
                    <div>
                        <p class="{{ $label }}">{{ $form['programs_label'] }}</p>
                        <div class="grid sm:grid-cols-2 gap-2">
                            @foreach($form['programs'] as $program)
                                <label class="flex items-start gap-2"><input type="checkbox" name="programs" value="{{ $program }}" class="mt-1"> <span>{{ $program }}</span></label>
                            @endforeach
                        </div>
                    </div>
                @endif
            </fieldset>

            <fieldset>
                <legend class="text-2xl font-bold mb-2">People</legend>
                <p class="mb-4 text-gray-700 dark:text-gray-300">
                    @if($minSize === $maxSize)
                        {{ $maxSize === 1 ? 'One person.' : "{$maxSize} people." }}
                    @else
                        {{ $minSize }} to {{ $maxSize }} people.
                    @endif
                    We need the real name of everyone, for example for the security staff on site. On the website, each person appears with the name they choose here.
                </p>
                <div id="members" class="space-y-4"></div>
                <button type="button" id="add-member" class="mt-4 px-4 py-2 rounded-lg border border-primary-600 text-primary-700 dark:text-primary-300 hover:bg-primary-50 dark:hover:bg-gray-800">Add a person</button>
                <template id="member-template">
                    <div class="member rounded-lg border border-gray-300 dark:border-gray-700 p-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <p class="font-semibold member-title"></p>
                            <button type="button" class="remove-member text-sm text-red-700 dark:text-red-400 underline">Remove</button>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3">
                            <label class="block"><span class="{{ $label }}">Real name</span><input data-field="real_name" maxlength="100" class="{{ $input }}"></label>
                            <label class="block"><span class="{{ $label }}">{{ $form['nickname_label'] }} (optional)</span><input data-field="nickname" maxlength="60" class="{{ $input }}"></label>
                        </div>
                        <div class="flex flex-wrap gap-x-6 gap-y-1">
                            <span class="font-semibold">Shown on the website as:</span>
                            <label class="flex items-center gap-2"><input type="radio" data-field="show_as" value="real" checked> real name</label>
                            <label class="flex items-center gap-2"><input type="radio" data-field="show_as" value="nick"> {{ strtolower($form['nickname_label']) }}</label>
                        </div>
                        @if($fields['discord'])
                            <label class="block"><span class="{{ $label }}">Discord username (optional)</span><input data-field="discord" maxlength="40" class="{{ $input }}"></label>
                        @endif
                    </div>
                </template>
            </fieldset>

            @if($fields['participation'])
                <fieldset>
                    <legend class="text-2xl font-bold mb-2">Participation</legend>
                    @if($form['onsite_capacity'])
                        <p class="mb-4 text-gray-700 dark:text-gray-300">There are {{ $form['onsite_capacity'] }} seats on site, given out in the order of registration. <span id="seats-left"></span></p>
                    @endif
                    <div class="space-y-2">
                        @foreach($wishes as $value => $text)
                            <label class="flex items-start gap-2"><input type="radio" name="wish" value="{{ $value }}" class="mt-1" @checked($value === 'onsite_preferred')> <span>{{ $text }}</span></label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            @if($fields['comment'])
                <div>
                    <label for="comment" class="text-2xl font-bold mb-2 block">Anything else?</label>
                    <textarea id="comment" name="comment" rows="4" maxlength="2000" class="{{ $input }}"></textarea>
                    <p class="{{ $hint }}">Ideas, requests or questions for the organisers.</p>
                </div>
            @endif

            {{-- People do not see this field. Bots fill it in. --}}
            <div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>

            @unless($edit)
                <fieldset class="space-y-2">
                    <legend class="sr-only">Consent</legend>
                    <label class="flex items-start gap-2"><input type="checkbox" name="consent_privacy" class="mt-1" required>
                        <span>I have read the <a class="text-primary-600 dark:text-primary-400 underline" href="{{ Hyde::relativeLink('imprint.html') }}#registration" target="_blank">privacy information on the registration</a>.</span></label>
                    <label class="flex items-start gap-2"><input type="checkbox" name="consent_names" class="mt-1" required>
                        <span>Everyone in this registration agrees that the team name and the name they chose appear on this website, for example with the project after the jam.</span></label>
                </fieldset>
                <div class="cf-turnstile" data-sitekey="{{ $form['turnstile_site_key'] }}"></div>
            @endunless

            <div id="registration-errors" class="hidden rounded-lg bg-red-100 dark:bg-red-900/40 text-red-900 dark:text-red-100 p-4" role="alert"></div>

            <div class="flex flex-wrap gap-4 items-center">
                <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-3 rounded-lg font-semibold">{{ $edit ? 'Save changes' : 'Register' }}</button>
                @if($edit)
                    <button type="button" id="cancel-registration" class="px-6 py-3 rounded-lg border border-red-700 text-red-700 dark:text-red-400">Cancel registration</button>
                    <button type="button" id="cancel-confirm" class="hidden px-6 py-3 rounded-lg bg-red-700 text-white">Yes, cancel our registration</button>
                @endif
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
@unless($mode === 'edit')
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endunless
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('registration-form');
    var mode = form.getAttribute('data-mode');
    var min = Number(form.getAttribute('data-min'));
    var max = Number(form.getAttribute('data-max'));
    var members = document.getElementById('members');
    var template = document.getElementById('member-template');
    var addButton = document.getElementById('add-member');
    var message = document.getElementById('registration-message');
    var errorBox = document.getElementById('registration-errors');
    var contact = @json((string) config('jam.email'));
    var token = new URLSearchParams(location.hash.slice(1)).get('t') || location.hash.slice(1);
    var placements = {
        onsite: 'You have a seat on site.',
        remote: 'You take part remotely.',
        waitlist: 'You are on the waiting list for a seat on site.'
    };

    function show(element, text, kind) {
        element.textContent = text;
        element.className = element.className.replace(/\b(hidden|bg-green-100|bg-red-100)\b/g, '') + (kind === 'error' ? ' bg-red-100 dark:bg-red-900/40' : ' bg-green-100 dark:bg-green-900/40');
        element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function renumber() {
        members.querySelectorAll('.member').forEach(function (row, index) {
            row.querySelector('.member-title').textContent = index === 0 ? 'Person 1 (team lead)' : 'Person ' + (index + 1);
            row.querySelectorAll('input[type=radio]').forEach(function (radio) { radio.name = 'show_as_' + index; });
            row.querySelector('.remove-member').classList.toggle('hidden', members.children.length <= Math.max(min, 1));
        });
        addButton.classList.toggle('hidden', members.children.length >= max);
    }

    function addMember(data) {
        var row = template.content.firstElementChild.cloneNode(true);
        data = data || {};
        row.querySelectorAll('[data-field]').forEach(function (input) {
            var field = input.getAttribute('data-field');
            if (input.type === 'radio') {
                input.checked = input.value === (data.show_as || 'real');
            } else if (data[field]) {
                input.value = data[field];
            }
        });
        row.querySelector('.remove-member').addEventListener('click', function () {
            row.remove();
            renumber();
        });
        members.appendChild(row);
        renumber();
    }

    function collect() {
        var value = function (name) { var field = form.elements[name]; return field ? field.value : ''; };
        var checked = form.querySelector('input[name=wish]:checked');

        return {
            team_name: value('team_name'),
            contact_email: value('contact_email'),
            programs: Array.prototype.map.call(form.querySelectorAll('input[name=programs]:checked'), function (box) { return box.value; }),
            wish: checked ? checked.value : 'onsite_preferred',
            comment: value('comment'),
            website: value('website'),
            consent_privacy: form.elements.consent_privacy ? form.elements.consent_privacy.checked : true,
            consent_names: form.elements.consent_names ? form.elements.consent_names.checked : true,
            turnstile: value('cf-turnstile-response'),
            members: Array.prototype.map.call(members.querySelectorAll('.member'), function (row) {
                var entry = {};
                row.querySelectorAll('[data-field]').forEach(function (input) {
                    var field = input.getAttribute('data-field');
                    if (input.type !== 'radio') {
                        entry[field] = input.value;
                    } else if (input.checked) {
                        entry.show_as = input.value;
                    }
                });
                return entry;
            })
        };
    }

    var labels = {
        team_name: 'Please enter a team name.',
        contact_email: 'Please enter a valid e-mail address.',
        members: 'Please enter between ' + min + ' and ' + max + ' people.',
        consent_privacy: 'Please confirm that you have read the privacy information.',
        consent_names: 'Please confirm that the chosen names may appear on the website.',
        wish: 'Please choose how your team takes part.'
    };

    function explain(fields) {
        return fields.map(function (field) {
            var match = field.match(/^members\.(\d+)\.(\w+)$/);
            if (match) {
                return 'Person ' + (Number(match[1]) + 1) + ': ' + (match[2] === 'real_name' ? 'please enter the real name.' : 'please enter the name to show, or show the real name.');
            }
            return labels[field] || field;
        }).join(' ');
    }

    function request(method, body) {
        var url = mode === 'edit' ? '/api/registration/self' : '/api/registration';
        var headers = { 'content-type': 'application/json' };
        if (mode === 'edit') {
            headers.authorization = 'Bearer ' + token;
        }
        return fetch(url, { method: method, headers: headers, body: body ? JSON.stringify(body) : undefined })
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (data) { return { status: response.status, data: data }; });
            });
    }

    function fail(result) {
        var texts = {
            closed: 'Registration is closed.',
            turnstile: 'The spam check failed. Please try again.',
            too_many: 'Too many registrations from your connection. Please try again later.',
            not_found: 'This link is not valid anymore. Maybe the registration was cancelled.'
        };
        var text = result.data.error === 'validation' ? explain(result.data.fields || []) : (texts[result.data.error] || 'Something went wrong. Please try again, or write to ' + contact + '.');
        show(errorBox, text, 'error');
        if (window.turnstile) {
            window.turnstile.reset();
        }
    }

    addButton.addEventListener('click', function () { addMember(); });

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        errorBox.classList.add('hidden');
        request(mode === 'edit' ? 'PUT' : 'POST', collect()).then(function (result) {
            if (result.status >= 300) {
                return fail(result);
            }
            if (mode === 'edit') {
                show(message, 'Your changes are saved. ' + placements[result.data.registration.placement], 'ok');
                return;
            }
            form.classList.add('hidden');
            show(message, 'Thank you! Your registration is received, and you will get a mail with a link to change it. ' + (placements[result.data.placement] || '') + ' We will confirm your registration soon.', 'ok');
        }).catch(function () { fail({ data: {} }); });
    });

    if (mode === 'edit') {
        var cancel = document.getElementById('cancel-registration');
        var confirmCancel = document.getElementById('cancel-confirm');
        cancel.addEventListener('click', function () { confirmCancel.classList.remove('hidden'); });
        confirmCancel.addEventListener('click', function () {
            request('DELETE').then(function (result) {
                if (result.status >= 300) {
                    return fail(result);
                }
                form.classList.add('hidden');
                show(message, 'Your registration is cancelled. You will get a mail about it.', 'ok');
            });
        });

        request('GET').then(function (result) {
            if (result.status >= 300) {
                form.classList.add('hidden');
                return show(message, 'This link is not valid anymore. Maybe the registration was cancelled.', 'error');
            }
            var registration = result.data.registration;
            form.elements.team_name.value = registration.team_name;
            form.elements.contact_email.value = registration.contact_email;
            if (form.elements.comment) {
                form.elements.comment.value = registration.comment;
            }
            form.querySelectorAll('input[name=programs]').forEach(function (box) { box.checked = registration.programs.indexOf(box.value) !== -1; });
            form.querySelectorAll('input[name=wish]').forEach(function (radio) { radio.checked = radio.value === registration.wish; });
            registration.members.forEach(addMember);
            show(message, placements[registration.placement] + (registration.status === 'confirmed' ? ' Your registration is confirmed.' : ' Your registration is not confirmed yet.'), 'ok');
            if (!result.data.open) {
                form.querySelectorAll('input, textarea, button[type=submit]').forEach(function (element) { element.disabled = true; });
            }
        });
        return;
    }

    for (var i = 0; i < Math.max(min, Math.min(max, 2)); i++) {
        addMember();
    }

    fetch('/api/registration', { cache: 'no-store' }).then(function (response) { return response.json(); }).then(function (status) {
        if (!status.open) {
            form.classList.add('hidden');
            document.getElementById('registration-closed').classList.remove('hidden');
        }
        var seats = document.getElementById('seats-left');
        if (seats && status.seats_left !== null) {
            seats.textContent = status.seats_left > 0 ? status.seats_left + ' seats are still free.' : 'All seats on site are taken. You can still take part remotely or join the waiting list.';
        }
    }).catch(function () {});
});
</script>
@endpush
