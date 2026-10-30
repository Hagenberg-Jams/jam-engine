{{-- The compiled Vite scripts --}}
@if(Vite::running())
    {{ Vite::assets(['resources/assets/app.js']) }}
@elseif(Asset::exists('app.js'))
    @php
        // Use relative path for development server compatibility
        $jsPath = '/media/app.js';
        $cacheBust = file_exists(base_path('_media/app.js')) ? '?v=' . substr(md5_file(base_path('_media/app.js')), 0, 8) : '';
    @endphp
    <script type="module" src="{{ $jsPath }}{{ $cacheBust }}"></script>
@endif

<script>
    function toggleTheme() {
        if (localStorage.getItem('color-theme') === 'dark' || !('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.remove("dark");
            localStorage.setItem('color-theme', 'light');
            document.getElementById('meta-color-scheme').setAttribute('content', 'light');
        } else {
            document.documentElement.classList.add("dark");
            localStorage.setItem('color-theme', 'dark');
            document.getElementById('meta-color-scheme').setAttribute('content', 'dark');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var nav = document.getElementById('main-navigation');
        var toggleBtn = document.getElementById('navigation-toggle-button');
        var navLinks = document.getElementById('main-navigation-links');
        var openIcon = document.getElementById('open-main-navigation-menu-icon');
        var closeIcon = document.getElementById('close-main-navigation-menu-icon');

        function closeNav() {
            if (nav) nav.classList.remove('mobile-nav-open');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            if (openIcon) openIcon.classList.remove('hidden');
            if (closeIcon) closeIcon.classList.add('hidden');
        }

        function openNav() {
            if (nav) nav.classList.add('mobile-nav-open');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
            if (openIcon) openIcon.classList.add('hidden');
            if (closeIcon) closeIcon.classList.remove('hidden');
        }

        function toggleNav() {
            if (nav && nav.classList.contains('mobile-nav-open')) {
                closeNav();
            } else {
                openNav();
            }
        }

        if (toggleBtn && navLinks) {
            toggleBtn.addEventListener('click', toggleNav);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeNav();
                document.querySelectorAll('.dropdown-container details[open]').forEach(function (d) {
                    d.removeAttribute('open');
                });
            }
        });

        document.addEventListener('click', function (e) {
            document.querySelectorAll('.dropdown-container details[open]').forEach(function (details) {
                if (!details.contains(e.target)) {
                    details.removeAttribute('open');
                }
            });
        });

        // Cookie consent (DSGVO)
        var cookieBanner = document.getElementById('cookie-banner');
        var cookieAccept = document.getElementById('cookie-accept');
        var cookieReject = document.getElementById('cookie-reject');
        var consentKey = 'cookie-consent-youtube';

        function refreshYoutubeEmbeds() {
            var accepted = localStorage.getItem(consentKey) === 'accepted';

            document.querySelectorAll('.youtube-embed').forEach(function (embed) {
                var placeholder = embed.querySelector('.youtube-cookie-placeholder');
                var container = embed.querySelector('.youtube-video-container');
                var thumbnail = embed.querySelector('.youtube-thumbnail');
                var youtubeId = embed.getAttribute('data-youtube-id');

                placeholder.classList.toggle('hidden', accepted);
                container.classList.toggle('hidden', !accepted);

                if (accepted && youtubeId && !thumbnail.getAttribute('src')) {
                    thumbnail.addEventListener('error', function () {
                        if (thumbnail.src.indexOf('hqdefault') === -1) {
                            thumbnail.src = 'https://img.youtube.com/vi/' + youtubeId + '/hqdefault.jpg';
                        }
                    });
                    thumbnail.src = 'https://img.youtube.com/vi/' + youtubeId + '/maxresdefault.jpg';
                }
            });
        }

        window.jamRefreshYoutubeEmbeds = refreshYoutubeEmbeds;

        function applyConsentState() {
            var consent = localStorage.getItem(consentKey);

            if (cookieBanner) cookieBanner.classList.toggle('hidden', consent === 'accepted' || consent === 'rejected');
            refreshYoutubeEmbeds();
        }

        function playYoutubeEmbed(embed) {
            var youtubeId = embed.getAttribute('data-youtube-id');
            if (!youtubeId || localStorage.getItem(consentKey) !== 'accepted') {
                return;
            }
            embed.querySelector('.youtube-placeholder').classList.add('hidden');
            embed.querySelector('.youtube-iframe-container').classList.remove('hidden');
            embed.querySelector('iframe').src = 'https://www.youtube-nocookie.com/embed/' + youtubeId + '?autoplay=1';
        }

        document.querySelectorAll('.youtube-embed').forEach(function (embed) {
            var player = embed.querySelector('.youtube-placeholder');
            embed.querySelector('.youtube-accept-cookies').addEventListener('click', acceptCookies);
            player.addEventListener('click', function () {
                playYoutubeEmbed(embed);
            });
            player.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    playYoutubeEmbed(embed);
                }
            });
        });

        function acceptCookies() {
            localStorage.setItem(consentKey, 'accepted');
            applyConsentState();
        }

        function rejectCookies() {
            localStorage.setItem(consentKey, 'rejected');
            applyConsentState();
        }

        function showCookieBanner() {
            if (cookieBanner) {
                cookieBanner.classList.remove('hidden');
                cookieBanner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        if (cookieAccept) cookieAccept.addEventListener('click', acceptCookies);
        if (cookieReject) cookieReject.addEventListener('click', rejectCookies);

        var cookieSettingsLink = document.getElementById('cookie-settings-link');
        if (cookieSettingsLink) cookieSettingsLink.addEventListener('click', function (e) {
            e.preventDefault();
            showCookieBanner();
        });

        applyConsentState();
    });
</script>

{{-- Add any extra scripts to include before the closing <body> tag --}}
@stack('scripts')

{{-- If the user has defined any custom scripts, render them here --}}
{!! config('hyde.scripts') !!}
{!! Includes::html('scripts') !!}

