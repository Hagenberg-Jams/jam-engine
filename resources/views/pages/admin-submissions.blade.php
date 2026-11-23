@extends('layouts.app')

@push('meta')
<meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
@php
    $button = 'px-3 py-1 rounded border border-primary-600 text-sm cursor-pointer transition-colors hover:bg-gray-100 dark:hover:bg-gray-800 active:bg-gray-200 dark:active:bg-gray-700';
@endphp

<section class="bg-primary-600 text-white py-12">
    <div class="container mx-auto px-4">
        <h1 class="text-3xl md:text-4xl font-bold mb-2">Submissions {{ $year }}</h1>
        <p class="text-primary-200">Only organisers signed in through Cloudflare Access can see this page.</p>
    </div>
</section>

<section class="py-8">
    <div class="container mx-auto px-4">
        <div id="admin-summary" class="flex flex-wrap gap-4 mb-6" aria-live="polite"></div>

        <div class="flex flex-wrap gap-3 items-center mb-6">
            <button type="button" id="refresh" class="{{ $button }}">Refresh</button>
            <label class="flex items-center gap-2 text-sm">Show
                <select id="filter" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1">
                    <option value="">all teams</option>
                    <option value="missing">teams without upload</option>
                    <option value="uploaded">teams with uploads</option>
                    <option value="late">teams with late uploads only</option>
                </select>
            </label>
            <label id="category-filter-label" class="hidden items-center gap-2 text-sm">in
                <select id="category-filter" class="rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-2 py-1">
                    <option value="">all categories</option>
                </select>
            </label>
        </div>

        <div id="admin-error" class="hidden rounded-lg p-4 mb-6 bg-red-100 dark:bg-red-900 text-red-900 dark:text-red-100" role="alert"></div>
        <div id="teams" class="space-y-6"><p>Loading…</p></div>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var teamsBox = document.getElementById('teams');
    var errorBox = document.getElementById('admin-error');
    var summary = document.getElementById('admin-summary');
    var filter = document.getElementById('filter');
    var categoryFilter = document.getElementById('category-filter');
    var data = null;

    function element(tag, className, text) {
        var node = document.createElement(tag);
        if (className) { node.className = className; }
        if (text !== undefined) { node.textContent = text; }
        return node;
    }

    function fileUrl(key, inline) { return '/api/admin/submissions/file?key=' + encodeURIComponent(key) + (inline ? '&inline=1' : ''); }
    function megabytes(bytes) { return (bytes / 1048576).toFixed(1) + ' MB'; }
    function when(iso) { return iso ? new Date(iso).toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : ''; }

    function badge(text, kind) {
        var colours = {
            ok: 'bg-green-100 text-green-900 dark:bg-green-900 dark:text-green-100',
            late: 'bg-amber-100 text-amber-900 dark:bg-amber-900 dark:text-amber-100',
            missing: 'bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100',
            category: 'bg-primary-100 text-primary-900 dark:bg-primary-900 dark:text-primary-100'
        };
        return element('span', 'text-xs font-semibold px-2 py-1 rounded ' + colours[kind], text);
    }

    function stat(label, value) {
        var box = element('div', 'rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-2');
        box.appendChild(element('div', 'text-2xl font-bold', String(value)));
        box.appendChild(element('div', 'text-sm text-gray-600 dark:text-gray-400', label));
        return box;
    }

    function link(href, text, download) {
        var a = element('a', 'underline text-primary-700 dark:text-primary-300', text);
        a.href = href;
        if (download) { a.setAttribute('download', ''); }
        return a;
    }

    function card(team) {
        var box = element('article', 'rounded-lg border border-gray-300 dark:border-gray-700 p-4 space-y-4');
        var head = element('div', 'flex flex-wrap items-center gap-3');
        head.appendChild(element('h2', 'text-xl font-bold', team.name));
        if (team.uploads.length === 0) { head.appendChild(badge('no upload yet', 'missing')); }
        else if (team.onTime) { head.appendChild(badge('on time', 'ok')); }
        else { head.appendChild(badge('late only', 'late')); }
        if (!team.listed) { head.appendChild(badge('not in the team list', 'missing')); }
        if (team.category) { head.appendChild(badge(team.category.name, 'category')); }
        else if (data.categories.length > 0 && team.uploads.length > 0) { head.appendChild(badge('no category', 'missing')); }
        var latest = Object.keys(team.latest).map(function (platform) { return (data.kind === 'game' ? platform + ' ' : '') + team.latest[platform]; });
        if (latest.length) { head.appendChild(element('span', 'text-sm text-gray-600 dark:text-gray-400', 'Latest: ' + latest.join(', '))); }
        box.appendChild(head);

        if (team.uploads.length > 0) {
            var table = element('table', 'w-full text-sm');
            var header = element('tr', 'text-left border-b border-gray-300 dark:border-gray-700');
            ['File', 'Version', data.kind === 'game' ? 'Platform' : null, 'Size', 'Uploaded'].filter(Boolean).forEach(function (label) {
                header.appendChild(element('th', 'py-1 pr-4', label));
            });
            table.appendChild(header);
            team.uploads.forEach(function (upload) {
                var row = element('tr', 'border-b border-gray-200 dark:border-gray-800' + (upload.latest ? ' font-semibold' : ' text-gray-600 dark:text-gray-400'));
                var file = element('td', 'py-1 pr-4 break-all');
                file.appendChild(link(fileUrl(upload.key), upload.file, true));
                row.appendChild(file);
                row.appendChild(element('td', 'py-1 pr-4', upload.version + (upload.latest ? ' (latest)' : '')));
                if (data.kind === 'game') { row.appendChild(element('td', 'py-1 pr-4', upload.platform)); }
                row.appendChild(element('td', 'py-1 pr-4 whitespace-nowrap', megabytes(upload.size)));
                var time = element('td', 'py-1 pr-4 whitespace-nowrap', when(upload.uploadedAt));
                if (upload.late) { time.appendChild(document.createTextNode(' ')); time.appendChild(badge('late', 'late')); }
                row.appendChild(time);
                table.appendChild(row);
            });
            var scroll = element('div', 'overflow-x-auto');
            scroll.appendChild(table);
            box.appendChild(scroll);
        }

        if (team.readme) {
            var details = element('details', 'rounded bg-gray-50 dark:bg-gray-900 p-3');
            var title = element('summary', 'cursor-pointer font-semibold', 'README.md: ' + team.readme.title + (team.readmes > 1 ? ' (' + team.readmes + ' versions uploaded, this is the latest)' : ''));
            details.appendChild(title);
            details.appendChild(element('pre', 'whitespace-pre-wrap text-sm mt-3 font-mono', team.readme.text));
            var download = element('p', 'mt-2 text-sm');
            download.appendChild(link(fileUrl(team.readme.key), 'Download README.md', true));
            details.appendChild(download);
            box.appendChild(details);
        }

        if (team.screenshots.length > 0) {
            var gallery = element('div', 'flex flex-wrap gap-3');
            team.screenshots.forEach(function (key, index) {
                var a = element('a', 'block');
                a.href = fileUrl(key);
                a.setAttribute('download', '');
                a.title = 'Download image ' + (index + 1);
                var img = element('img', 'h-24 w-auto rounded border border-gray-300 dark:border-gray-700');
                img.loading = 'lazy';
                img.alt = (data.kind === 'game' ? 'Screenshot ' : 'Still ') + (index + 1) + ' of ' + team.name;
                img.src = fileUrl(key, true);
                a.appendChild(img);
                gallery.appendChild(a);
            });
            box.appendChild(gallery);
        }

        return box;
    }

    function render() {
        var teams = data.teams.filter(function (team) {
            if (filter.value === 'missing') { return team.uploads.length === 0; }
            if (filter.value === 'uploaded') { return team.uploads.length > 0; }
            if (filter.value === 'late') { return team.uploads.length > 0 && !team.onTime; }
            return true;
        }).filter(function (team) {
            return !categoryFilter.value || (team.category ? team.category.key : 'none') === categoryFilter.value;
        });

        summary.innerHTML = '';
        var uploaded = data.teams.filter(function (team) { return team.uploads.length > 0; });
        summary.appendChild(stat('teams', data.teams.length));
        summary.appendChild(stat('with uploads', uploaded.length));
        summary.appendChild(stat('without upload', data.teams.length - uploaded.length));
        summary.appendChild(stat('late only', uploaded.filter(function (team) { return !team.onTime; }).length));
        data.categories.forEach(function (category) {
            summary.appendChild(stat(category.name, data.teams.filter(function (team) { return team.category && team.category.key === category.key; }).length));
        });
        if (data.deadline) { summary.appendChild(stat('deadline', when(data.deadline))); }

        teamsBox.innerHTML = '';
        if (teams.length === 0) { teamsBox.appendChild(element('p', '', 'No teams to show.')); }
        teams.forEach(function (team) { teamsBox.appendChild(card(team)); });
    }

    function load() {
        errorBox.classList.add('hidden');
        fetch('/api/admin/submissions', { cache: 'no-store', credentials: 'same-origin' }).then(function (response) {
            if (response.status === 401) { throw new Error('You are not signed in. Reload the page to sign in through Cloudflare Access.'); }
            if (response.status === 503) { throw new Error('The submission storage is not set up for this deployment.'); }
            if (!response.ok) { throw new Error('Loading failed (' + response.status + ').'); }
            return response.json();
        }).then(function (result) {
            data = result;
            data.categories = data.categories || [];
            var chosen = categoryFilter.value;
            categoryFilter.length = 1;
            data.categories.concat([{ key: 'none', name: 'no category' }]).forEach(function (category) {
                categoryFilter.appendChild(new Option(category.name, category.key, false, category.key === chosen));
            });
            var categoryLabel = document.getElementById('category-filter-label');
            categoryLabel.classList.toggle('hidden', data.categories.length === 0);
            categoryLabel.classList.toggle('flex', data.categories.length > 0);
            render();
        }).catch(function (error) {
            errorBox.textContent = error.message;
            errorBox.classList.remove('hidden');
            teamsBox.innerHTML = '';
        });
    }

    document.getElementById('refresh').addEventListener('click', load);
    filter.addEventListener('change', function () { if (data) { render(); } });
    categoryFilter.addEventListener('change', function () { if (data) { render(); } });
    load();
});
</script>
@endpush
