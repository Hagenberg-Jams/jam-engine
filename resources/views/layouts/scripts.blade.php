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
        var youtubeAcceptCookies = document.getElementById('youtube-accept-cookies');
        var cookiePlaceholder = document.getElementById('youtube-cookie-placeholder');
        var videoContainer = document.getElementById('youtube-video-container');
        var consentKey = 'cookie-consent-youtube';

        function applyConsentState() {
            var consent = localStorage.getItem(consentKey);

            if (consent === 'accepted') {
                if (cookieBanner) cookieBanner.classList.add('hidden');
                if (cookiePlaceholder) cookiePlaceholder.classList.add('hidden');
                if (videoContainer) videoContainer.classList.remove('hidden');
            } else if (consent === 'rejected') {
                if (cookieBanner) cookieBanner.classList.add('hidden');
                if (cookiePlaceholder) cookiePlaceholder.classList.remove('hidden');
                if (videoContainer) videoContainer.classList.add('hidden');
            } else {
                if (cookieBanner) cookieBanner.classList.remove('hidden');
                if (cookiePlaceholder) cookiePlaceholder.classList.remove('hidden');
                if (videoContainer) videoContainer.classList.add('hidden');
            }
        }

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
        if (youtubeAcceptCookies) youtubeAcceptCookies.addEventListener('click', acceptCookies);

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

