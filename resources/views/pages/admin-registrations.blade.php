@extends('layouts.app')

@push('meta')
<meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
@php
    $button = 'px-3 py-1 rounded border text-sm';
@endphp

<section class="bg-primary-600 text-white py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">Registrations {{ $year }}</h1>
        <p class="text-primary-200">Only organisers signed in through Cloudflare Access can see this page.</p>
    </div>
</section>

<section class="py-8">
    <div class="container mx-auto px-4">
        <div id="admin-summary" class="flex flex-wrap gap-4 mb-6" aria-live="polite"></div>

        <div class="flex flex-wrap gap-3 items-center mb-6">
            <a class="{{ $button }} border-primary-600" href="/api/admin/export?placement=onsite">People on site (CSV)</a>
            <a class="{{ $button }} border-primary-600" href="/api/admin/export">Everyone (CSV)</a>
            <a class="{{ $button }} border-primary-600" href="/api/admin/export?format=teams">Teams for the website (JSON)</a>
            <button type="button" id="copy-discord" class="{{ $button }} border-primary-600">Copy Discord names</button>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" id="send-mail" checked> Mail the team about changes</label>
            <label class="flex items-center gap-2 text-sm">Show
                <select id="filter-status" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1">
                    <option value="">all but cancelled</option>
                    <option value="pending">pending</option>
                    <option value="confirmed">confirmed</option>
                    <option value="declined">declined</option>
                    <option value="cancelled">cancelled</option>
                </select>
            </label>
        </div>

        <div id="admin-error" class="hidden rounded-lg bg-red-100 dark:bg-red-900/40 p-4 mb-6" role="alert"></div>
        <div id="admin-list" class="space-y-4"></div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('admin-list');
    var summary = document.getElementById('admin-summary');
    var errorBox = document.getElementById('admin-error');
    var filter = document.getElementById('filter-status');
    var state = { registrations: [], capacity: null, seats_taken: 0 };
    var labels = {
        pending: 'Not confirmed yet',
        confirmed: 'Confirmed',
        declined: 'Declined',
        cancelled: 'Cancelled by the team',
        onsite: 'On site',
        remote: 'Remote',
        waitlist: 'Waiting list',
        onsite_preferred: 'if full: remote',
        onsite_only: 'if full: waiting list',
        remote_wish: 'remote'
    };
    var label = function (value) { return labels[value] || value; };

    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function fail(text) {
        errorBox.textContent = text;
        errorBox.classList.remove('hidden');
    }

    function load() {
        fetch('/api/admin/registrations', { cache: 'no-store', credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) { throw new Error(response.status); }
                return response.json();
            })
            .then(function (data) { state = data; render(); })
            .catch(function (error) { fail('Could not load the registrations (' + error.message + '). Reload the page to sign in again.'); });
    }

    function act(registration, body) {
        body.id = registration.id;
        body.notify = document.getElementById('send-mail').checked;
        return fetch('/api/admin/registrations', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'content-type': 'application/json' },
            body: JSON.stringify(body)
        }).then(function (response) {
            if (!response.ok) { throw new Error(response.status); }
            load();
        }).catch(function (error) { fail('The change failed (' + error.message + ').'); });
    }

    function tile(label, value) {
        var box = element('div', 'rounded-lg bg-gray-200 dark:bg-gray-800 px-4 py-3');
        box.appendChild(element('div', 'text-2xl font-bold', String(value)));
        box.appendChild(element('div', 'text-sm text-gray-600 dark:text-gray-400', label));
        return box;
    }

    function render() {
        var active = state.registrations.filter(function (r) { return r.status !== 'cancelled' && r.status !== 'declined'; });
        var sum = function (list, field) { return list.reduce(function (total, r) { return total + r[field]; }, 0); };
        var placed = function (placement) { return active.filter(function (r) { return r.placement === placement; }); };

        summary.replaceChildren(
            tile('teams', active.length),
            tile('people', sum(active, 'people')),
            tile(state.capacity ? 'on site of ' + state.capacity : 'on site', sum(placed('onsite'), 'onsite_people')),
            tile('remote', sum(active, 'people') - sum(placed('onsite'), 'onsite_people') - sum(placed('waitlist'), 'onsite_people')),
            tile('waiting for a seat', sum(placed('waitlist'), 'onsite_people')),
            tile('pending', state.registrations.filter(function (r) { return r.status === 'pending'; }).length)
        );

        var shown = state.registrations.filter(function (r) { return filter.value ? r.status === filter.value : r.status !== 'cancelled'; });
        var firstWaiting = state.registrations.find(function (r) { return r.placement === 'waitlist' && r.status !== 'cancelled' && r.status !== 'declined'; });
        var free = state.capacity ? state.capacity - state.seats_taken : null;

        list.replaceChildren.apply(list, shown.map(function (registration) {
            var card = element('article', 'rounded-lg border border-gray-300 dark:border-gray-700 p-4');
            var head = element('div', 'flex flex-wrap justify-between gap-2');
            head.appendChild(element('h2', 'text-xl font-bold', registration.team_name));
            head.appendChild(element('span', 'text-sm', label(registration.status) + ' · ' + label(registration.placement) + ' · ' + registration.people + ' people, ' + registration.onsite_people + ' on site · ' + new Date(registration.created_at).toLocaleString()));
            card.appendChild(head);

            if (registration === firstWaiting && free !== null && free >= registration.onsite_people) {
                card.appendChild(element('p', 'mt-2 font-semibold text-green-800 dark:text-green-300', 'Next on the waiting list, and ' + free + ' seats are free.'));
            }

            card.appendChild(element('p', 'text-sm mt-1', registration.contact_email + (registration.programs.length ? ' · ' + registration.programs.join(', ') : '') + (state.capacity ? ' · ' + label(registration.wish === 'remote' ? 'remote_wish' : registration.wish) : '')));

            var people = element('ul', 'mt-2 list-disc pl-6');
            registration.members.forEach(function (member) {
                var shownAs = member.show_as === 'nick' && member.nickname ? ' → shown as ' + member.nickname : '';
                people.appendChild(element('li', '', member.real_name + shownAs + (member.discord ? ' · Discord: ' + member.discord : '') + (member.remote ? ' · remote' : '')));
            });
            card.appendChild(people);

            if (registration.comment) {
                card.appendChild(element('p', 'mt-2 italic', registration.comment));
            }

            var actions = element('div', 'flex flex-wrap gap-2 mt-3 items-center');
            var add = function (label, body, className) {
                var button = element('button', '{{ $button }} ' + (className || 'border-gray-400'), label);
                button.type = 'button';
                button.addEventListener('click', function () { act(registration, body); });
                actions.appendChild(button);
                return button;
            };
            if (registration.status !== 'confirmed') { add('Confirm', { action: 'confirm' }, 'border-green-700 text-green-800 dark:text-green-300'); }
            if (registration.status !== 'declined') { add('Decline', { action: 'decline' }, 'border-red-700 text-red-800 dark:text-red-300'); }
            ['onsite', 'remote', 'waitlist'].forEach(function (placement) {
                if (registration.placement !== placement) { add('Move to ' + label(placement).toLowerCase(), { action: 'place', placement: placement }); }
            });
            var remove = element('button', '{{ $button }} border-red-700 ml-auto', 'Delete');
            remove.type = 'button';
            remove.addEventListener('click', function () {
                if (remove.getAttribute('data-armed')) {
                    act(registration, { action: 'delete' });
                } else {
                    remove.setAttribute('data-armed', '1');
                    remove.textContent = 'Really delete?';
                }
            });
            actions.appendChild(remove);
            card.appendChild(actions);

            return card;
        }));

        if (shown.length === 0) {
            list.replaceChildren(element('p', '', 'No registrations.'));
        }
    }

    filter.addEventListener('change', render);
    document.getElementById('copy-discord').addEventListener('click', function () {
        var names = state.registrations.filter(function (r) { return r.status !== 'cancelled' && r.status !== 'declined'; })
            .flatMap(function (r) { return r.members.map(function (m) { return m.discord; }); }).filter(Boolean);
        navigator.clipboard.writeText(names.join('\n'));
    });

    load();
});
</script>
@endpush
