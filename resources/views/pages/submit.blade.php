@extends('layouts.app')

@push('meta')
<meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
@php
    use HagenbergJams\JamEngine\Submission;

    $settings = config('jam.submission');
    $function = Submission::functionConfig();
    $game = Submission::kind() === 'game';
    $deadline = Submission::deadline();
    $closes = Submission::closes();
    $shots = $function['screenshots'];
    $project = (string) config('jam.labels.project_lower', 'project');
    $accept = implode(',', array_map(fn(string $extension): string => ".{$extension}", $function['extensions']));
    $input = 'w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500';
    $label = 'block font-semibold mb-1';
    $hint = 'text-sm text-gray-600 dark:text-gray-400 mt-1';
    $button = 'px-6 py-3 rounded-lg bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white font-semibold disabled:opacity-50';
    $client = [
        'kind' => Submission::kind(),
        'platforms' => $function['platforms'],
        'extensions' => $function['extensions'],
        'maxSize' => $function['maxSize'],
        'screenshots' => $shots,
        'template' => Submission::readmeTemplate(),
        'contact' => (string) config('jam.email'),
    ];
@endphp

<section class="bg-primary-600 text-white py-16">
    <div class="container mx-auto px-4">
        <h1 class="text-4xl md:text-5xl font-bold mb-2">Submit your {{ ucfirst($project) }}</h1>
        <p class="text-xl text-primary-200">{{ config('hyde.name') }} {{ $year }}@if($deadline) · Deadline {{ $deadline->format('l, j F Y, H:i') }}@endif</p>
    </div>
</section>

<section class="py-12">
    <div class="container mx-auto px-4 max-w-3xl space-y-8">
        <div id="submit-closed" class="{{ Submission::isOpen() ? 'hidden' : '' }} rounded-lg bg-gray-200 dark:bg-gray-800 p-6 text-lg">
            Uploads are closed. If you need to send us something, write to <a class="text-primary-600 dark:text-primary-400 underline" href="mailto:{{ config('jam.email') }}">{{ config('jam.email') }}</a>.
        </div>

        <div id="submit-late" class="hidden rounded-lg bg-amber-100 dark:bg-amber-900 text-amber-900 dark:text-amber-100 p-4">
            The deadline has passed. You can still upload, for example a bugfix, but the upload is marked as late.
        </div>

        @if(!empty($settings['texts']['intro']))
            <div class="prose dark:prose-invert max-w-none">{!! \Illuminate\Support\Str::markdown((string) $settings['texts']['intro']) !!}</div>
        @endif

        <div class="rounded-lg border border-gray-300 dark:border-gray-700 p-6 space-y-3">
            <h2 class="text-2xl font-bold">What to upload</h2>
            <ul class="list-disc pl-6 space-y-2">
                @if($game)
                    <li><strong>Your game as a ZIP file</strong>, one for each platform ({{ implode(', ', $function['platforms']) }}). Make sure it runs without anything else installed.</li>
                @else
                    <li><strong>Your animation as a video file</strong> ({{ strtoupper(implode(', ', $function['extensions'])) }}), in the best quality you have.</li>
                @endif
                <li><strong>README.md</strong> with the name of your {{ $project }}, your team, its members and a short description in English@if($game), and the controls@endif. Start from our template and fill in every section: <a href="/submit/README.md" download="README.md" class="font-semibold underline text-primary-700 dark:text-primary-300">Download the README template</a></li>
                @if($shots['min'] > 0)
                    <li><strong>{{ $shots['min'] }} {{ $game ? 'screenshots' : 'stills' }}</strong>@if($shots['width'] > 0) of at least {{ $shots['width'] }}×{{ $shots['height'] }} pixels @endif (PNG, JPG or WebP).@if($game) One should show the start screen, the others different phases of the game.@endif</li>
                @endif
            </ul>
            <p>The README and the {{ $game ? 'screenshots' : 'stills' }} are needed once. When you upload a new version later, for example a bugfix, you only need the new file. The version number and the file name are chosen for you. Nothing you upload is overwritten.</p>
        </div>

        <div id="submit-message" class="hidden rounded-lg p-6 text-lg" role="status" aria-live="polite"></div>

        <form id="submit-team" class="{{ Submission::isOpen() ? '' : 'hidden' }} space-y-4" novalidate>
            <h2 class="text-2xl font-bold">1. Your team</h2>
            <div>
                <label for="team" class="{{ $label }}">Team</label>
                <select id="team" class="{{ $input }}" required><option value="">Loading the teams…</option></select>
                <p class="{{ $hint }}">Your team is missing? Write to <a class="underline" href="mailto:{{ config('jam.email') }}">{{ config('jam.email') }}</a> or ask us on site.</p>
            </div>
            <div>
                <label for="code" class="{{ $label }}">Upload code</label>
                <input id="code" class="{{ $input }}" autocomplete="off" required>
                <p class="{{ $hint }}">You find it in our announcement on Discord, or ask us on site.</p>
            </div>
            <button type="submit" class="{{ $button }}">Continue</button>
        </form>

        <form id="submit-files" class="hidden space-y-6" novalidate>
            <h2 class="text-2xl font-bold">2. Your files for <span id="team-name"></span></h2>

            <div id="previous" class="hidden rounded-lg bg-gray-100 dark:bg-gray-800 p-4">
                <p class="font-semibold mb-2">Already uploaded</p>
                <ul id="previous-list" class="space-y-1 text-sm"></ul>
            </div>

            @if($game)
                <div>
                    <label for="platform" class="{{ $label }}">Platform</label>
                    <select id="platform" class="{{ $input }}">
                        @foreach($function['platforms'] as $platform)
                            <option value="{{ $platform }}">{{ $platform }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label for="file" class="{{ $label }}">{{ $game ? 'ZIP file' : 'Video file' }}</label>
                <input id="file" type="file" accept="{{ $accept }}" class="{{ $input }}" required>
                <p class="{{ $hint }}">At most {{ number_format($function['maxSize'] / 1073741824, 0) }} GB.</p>
            </div>

            <div>
                <label for="version" class="{{ $label }}">Version</label>
                <input id="version" class="{{ $input }}" pattern="\d+\.\d+\.\d+" required>
                <p class="{{ $hint }}" id="version-hint">We suggest the next version. Change it if you like, for example to 2.0.0, but it must be higher than the last one.</p>
            </div>

            <div>
                <label for="readme" class="{{ $label }}">README.md <span id="readme-optional" class="hidden font-normal">(optional, replaces the one you uploaded before)</span></label>
                <input id="readme" type="file" accept=".md,text/markdown,text/plain" class="{{ $input }}">
                <p class="{{ $hint }}"><button type="button" id="template" class="underline text-primary-700 dark:text-primary-300">Download the template with your team filled in</button>, and fill in every section.</p>
                <ul id="readme-problems" class="hidden mt-2 list-disc pl-6 text-red-700 dark:text-red-400"></ul>
            </div>

            @if($shots['max'] > 0)
                <div>
                    <label for="screenshots" class="{{ $label }}">{{ $game ? 'Screenshots' : 'Stills' }} <span id="screenshots-optional" class="hidden font-normal">(optional, replace the ones you uploaded before)</span></label>
                    <input id="screenshots" type="file" accept="image/png,image/jpeg,image/webp" multiple class="{{ $input }}">
                    <p class="{{ $hint }}">{{ $shots['min'] }} to {{ $shots['max'] }} images@if($shots['width'] > 0), at least {{ $shots['width'] }}×{{ $shots['height'] }} pixels@endif. Select them all at once.</p>
                    <ul id="screenshot-problems" class="hidden mt-2 list-disc pl-6 text-amber-800 dark:text-amber-300"></ul>
                </div>
            @endif

            <div class="cf-turnstile" data-sitekey="{{ Submission::turnstileSiteKey() }}"></div>

            <ul id="files-errors" class="hidden list-disc pl-6 text-red-700 dark:text-red-400" role="alert"></ul>

            <div id="progress" class="hidden space-y-2" aria-live="polite">
                <div class="h-3 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden"><div id="progress-bar" class="h-3 bg-primary-600 w-0 transition-all"></div></div>
                <p id="progress-text" class="text-sm">Preparing…</p>
            </div>

            <div class="flex flex-wrap gap-4 items-center">
                <button type="submit" id="upload" class="{{ $button }}">Upload</button>
                <button type="button" id="change-team" class="underline">Choose another team</button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var page = @json($client);
    var $ = function (id) { return document.getElementById(id); };
    var teamForm = $('submit-team');
    var filesForm = $('submit-files');
    var message = $('submit-message');
    var state = null;

    var texts = {
        code: 'The upload code is not right.',
        team: 'Please choose your team.',
        closed: 'Uploads are closed.',
        not_configured: 'Uploads are not set up yet. Please tell the organisers.',
        turnstile: 'The spam check failed. Please try again.',
        exists: 'This version was uploaded already. Please choose a higher version.',
        session: 'The upload took too long. Please start again.',
        incomplete: 'The file did not arrive completely. Please try again.',
        screenshots_missing: 'Not all images arrived. Please try again.',
        platform: 'Please choose a platform.',
        extension: 'Please choose a ' + page.extensions.join(' or ').toUpperCase() + ' file.',
        size: 'The file is empty or too large.',
        version: 'Please enter a version like 1.0.0.',
        readme_required: 'Please add your README.md. It is needed with your first upload.',
        readme_size: 'The README.md is too large.',
        screenshots_required: 'Please add at least ' + page.screenshots.min + ' images. They are needed with your first upload.',
        screenshots_count: 'Please add ' + page.screenshots.min + ' to ' + page.screenshots.max + ' images.',
        screenshots_type: 'Images must be PNG, JPG or WebP files of at most 25 MB.',
        network: 'The connection failed. Please check it and try again.'
    };

    function describe(error) {
        if (error.code === 'title') { return 'README.md: please replace the first heading "' + error.section + '" with your title.'; }
        if (error.code === 'missing') { return 'README.md: the section "' + error.section + '" is missing.'; }
        if (error.code === 'empty') { return 'README.md: please fill in the section "' + error.section + '".'; }
        if (error.code === 'version_low') { return 'The version must be higher than ' + error.latest + ', your last upload for this platform.'; }

        return texts[error.code] || texts[error] || 'Something went wrong (' + (error.code || error) + '). Please try again.';
    }

    function show(text, kind) {
        message.textContent = text;
        message.className = 'rounded-lg p-6 text-lg ' + (kind === 'error' ? 'bg-red-100 dark:bg-red-900 text-red-900 dark:text-red-100' : 'bg-green-100 dark:bg-green-900 text-green-900 dark:text-green-100');
    }

    function list(element, items) {
        element.innerHTML = '';
        items.forEach(function (item) { var li = document.createElement('li'); li.textContent = item; element.appendChild(li); });
        element.classList.toggle('hidden', items.length === 0);
    }

    async function api(path, body, options) {
        options = options || {};
        var response = await fetch('/api/submission/' + path, {
            method: options.method || 'POST',
            headers: options.headers || { 'content-type': 'application/json' },
            body: options.raw !== undefined ? options.raw : JSON.stringify(body)
        });
        var data = await response.json().catch(function () { return {}; });

        return { ok: response.ok, status: response.status, data: data };
    }

    function megabytes(bytes) { return (bytes / 1048576).toFixed(1) + ' MB'; }

    // Teams and state

    fetch('/api/submission', { cache: 'no-store' }).then(function (response) { return response.json(); }).then(function (status) {
        var select = $('team');
        select.innerHTML = '<option value="">Please choose…</option>';
        status.teams.forEach(function (team) {
            var option = document.createElement('option');
            option.value = team.slug;
            option.textContent = team.name;
            select.appendChild(option);
        });
        if (!status.open) { $('submit-closed').classList.remove('hidden'); teamForm.classList.add('hidden'); }
        $('submit-late').classList.toggle('hidden', !status.open || !status.late);
    }).catch(function () { show(texts.network, 'error'); });

    teamForm.addEventListener('submit', function (event) {
        event.preventDefault();
        message.classList.add('hidden');
        if (!$('team').value) { show(texts.team, 'error'); return; }
        loadTeam();
    });

    async function loadTeam() {
        var result = await api('team', { team: $('team').value, code: $('code').value }).catch(function () { return { data: { error: 'network' } }; });
        if (!result.ok) { show(describe(result.data.error), 'error'); return; }

        state = result.data;
        $('team-name').textContent = state.team;
        list($('previous-list'), state.uploads.map(function (upload) {
            return upload.file + ' · ' + megabytes(upload.size) + (upload.late ? ' · late' : '');
        }));
        $('previous').classList.toggle('hidden', state.uploads.length === 0);
        $('readme-optional').classList.toggle('hidden', !state.readme);
        if ($('screenshots-optional')) { $('screenshots-optional').classList.toggle('hidden', state.screenshots === 0); }
        suggest();
        teamForm.classList.add('hidden');
        filesForm.classList.remove('hidden');
    }

    function platform() { return page.kind === 'game' ? $('platform').value : 'video'; }

    function suggest() {
        $('version').value = state.suggested[platform()] || '1.0.0';
        var latest = state.latest[platform()];
        $('version-hint').textContent = latest
            ? 'Your last upload' + (page.kind === 'game' ? ' for ' + platform() : '') + ' was ' + latest + '. We suggest ' + $('version').value + '. For bigger changes, you can raise the first or second number instead.'
            : 'This is your first upload' + (page.kind === 'game' ? ' for ' + platform() : '') + ', so we suggest 1.0.0.';
    }

    if ($('platform')) { $('platform').addEventListener('change', suggest); }

    $('change-team').addEventListener('click', function () {
        filesForm.classList.add('hidden');
        teamForm.classList.remove('hidden');
        message.classList.add('hidden');
    });

    $('template').addEventListener('click', function () {
        var text = page.template.replace('{team}', state ? state.team : '');
        var link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob([text], { type: 'text/markdown' }));
        link.download = 'README.md';
        link.click();
        setTimeout(function () { URL.revokeObjectURL(link.href); }, 1000);
    });

    // Checks before the upload

    async function checkReadme(file) {
        if (!file) { return []; }
        var text = await file.text();
        var result = await api('check', { readme: text });
        if (!result.ok) { return [describe(result.data.error)]; }

        return result.data.errors.map(describe);
    }

    $('readme').addEventListener('change', async function () {
        list($('readme-problems'), await checkReadme($('readme').files[0]).catch(function () { return []; }));
    });

    async function checkScreenshots(files) {
        var warnings = [];
        for (var i = 0; i < files.length; i++) {
            try {
                var image = await createImageBitmap(files[i]);
                if (image.width < page.screenshots.width || image.height < page.screenshots.height) {
                    warnings.push(files[i].name + ' has ' + image.width + '×' + image.height + ' pixels, we would like at least ' + page.screenshots.width + '×' + page.screenshots.height + '.');
                }
                image.close();
            } catch (error) {
                warnings.push(files[i].name + ' could not be read as an image.');
            }
        }

        return warnings;
    }

    if ($('screenshots')) {
        $('screenshots').addEventListener('change', async function () {
            list($('screenshot-problems'), await checkScreenshots($('screenshots').files));
        });
    }

    // Upload

    function progress(done, total, text) {
        $('progress').classList.remove('hidden');
        $('progress-bar').style.width = Math.round(done / total * 100) + '%';
        $('progress-text').textContent = text;
    }

    async function withRetry(task) {
        for (var attempt = 1; ; attempt++) {
            try {
                var result = await task();
                if (result.ok || attempt >= 4 || (result.status >= 400 && result.status < 500)) { return result; }
            } catch (error) {
                if (attempt >= 4) { return { ok: false, data: { error: 'network' } }; }
            }
            await new Promise(function (resolve) { setTimeout(resolve, attempt * 2000); });
        }
    }

    function resetTurnstile() { if (window.turnstile) { window.turnstile.reset(); } }

    filesForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        var file = $('file').files[0];
        var readme = $('readme').files[0];
        var shots = $('screenshots') ? Array.prototype.slice.call($('screenshots').files) : [];
        var errors = [];
        var extension = file ? file.name.split('.').pop().toLowerCase() : '';

        if (!file) { errors.push('Please choose your ' + (page.kind === 'game' ? 'ZIP file.' : 'video file.')); }
        else if (page.extensions.indexOf(extension) === -1) { errors.push(texts.extension); }
        else if (file.size > page.maxSize) { errors.push('The file has ' + megabytes(file.size) + ', at most ' + megabytes(page.maxSize) + ' are possible.'); }
        if (!readme && !state.readme) { errors.push(texts.readme_required); }
        errors = errors.concat(await checkReadme(readme).catch(function () { return []; }));
        if (shots.length === 0 && page.screenshots.min > 0 && state.screenshots === 0) { errors.push(texts.screenshots_required); }
        if (shots.length > 0 && (shots.length < page.screenshots.min || shots.length > page.screenshots.max)) { errors.push(texts.screenshots_count); }
        list($('files-errors'), errors);
        if (errors.length > 0) { return; }

        var button = $('upload');
        button.disabled = true;
        message.classList.add('hidden');
        progress(0, 1, 'Starting the upload…');

        var start = await api('start', {
            code: $('code').value,
            team: $('team').value,
            turnstile: (filesForm.querySelector('[name="cf-turnstile-response"]') || {}).value,
            platform: platform(),
            version: $('version').value.trim(),
            extension: extension,
            size: file.size,
            readme: readme ? await readme.text() : null,
            screenshots: shots.map(function (shot) { return { type: shot.type, size: shot.size }; })
        }).catch(function () { return { data: { error: 'network' } }; });

        if (!start.ok) {
            button.disabled = false;
            $('progress').classList.add('hidden');
            resetTurnstile();
            list($('files-errors'), start.data.errors ? start.data.errors.map(describe) : [describe(start.data.error)]);
            return;
        }

        var token = start.data.token;
        var auth = function (type) { return { authorization: 'Bearer ' + token, 'content-type': type || 'application/octet-stream' }; };
        var parts = [];
        var failed = null;
        var total = file.size + shots.reduce(function (sum, shot) { return sum + shot.size; }, 0);
        var sent = 0;

        for (var n = 1; n <= start.data.parts && !failed; n++) {
            var chunk = file.slice((n - 1) * start.data.partSize, n * start.data.partSize);
            progress(sent, total, 'Uploading ' + start.data.file + ': ' + megabytes(sent) + ' of ' + megabytes(file.size));
            var part = await withRetry(function () { return api('part?n=' + n, null, { method: 'PUT', headers: auth(), raw: chunk }); });
            if (!part.ok) { failed = part.data.error || 'network'; break; }
            parts.push(part.data.part);
            sent += chunk.size;
        }

        for (var i = 0; i < shots.length && !failed; i++) {
            progress(sent, total, 'Uploading image ' + (i + 1) + ' of ' + shots.length + '…');
            var shot = shots[i];
            var stored = await withRetry(function () { return api('screenshot?i=' + (i + 1), null, { method: 'PUT', headers: auth(shot.type), raw: shot }); });
            if (!stored.ok) { failed = stored.data.error || 'network'; break; }
            sent += shot.size;
        }

        var done = failed ? null : await withRetry(function () { return api('complete', { parts: parts }, { headers: auth('application/json') }); });
        button.disabled = false;
        resetTurnstile();

        if (failed || !done.ok) {
            await api('abort', {}, { headers: auth('application/json') }).catch(function () {});
            $('progress').classList.add('hidden');
            show(describe(failed || done.data.error) + ' Nothing was stored, you can upload again.', 'error');
            return;
        }

        progress(1, 1, 'Done.');
        show('Thank you! We received ' + done.data.file + ' (' + megabytes(done.data.size) + ').' + (done.data.late ? ' It came after the deadline and is marked as late.' : '')
            + ' To upload a new version later, come back to this page.', 'success');
        filesForm.reset();
        list($('readme-problems'), []);
        if ($('screenshot-problems')) { list($('screenshot-problems'), []); }
        $('progress').classList.add('hidden');
        await loadTeam();
        message.scrollIntoView({ behavior: 'smooth' });
    });
});
</script>
@endpush
