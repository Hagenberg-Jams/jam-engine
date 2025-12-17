@php
    $companyName = config('gamejam.company.name', 'University of Applied Sciences Upper Austria – Department of Digital Media');
    $email = config('gamejam.email', 'info@hagenberg-gamejam.at');
    $companyAddress = config('gamejam.company.address', '');
    $companyUrl = config('gamejam.company.url', '');
    $logoLight = ltrim((string) config('gamejam.branding.logo_light', 'hagenberg_game_jam_logo_white.svg'), '/');
    $instagramUrl = config('gamejam.social.instagram', '');
    $discordUrl = config('gamejam.social.discord', '');
    $githubUrl = config('gamejam.social.github', '');
@endphp

<footer class="bg-gray-800 text-white mt-auto">
    <!-- Footer Widget Area -->
        <div class="bg-cover bg-center relative" style="background-image: url('/media/gamejam_footer.jpg');">
        <div class="absolute inset-0 bg-black bg-opacity-60"></div>
        <div class="container mx-auto px-4 py-16 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Logo -->
                <div>
                    <img src="/media/{{ $logoLight }}" alt="{{ config('hyde.name', 'Hagenberg Game Jam') }}" class="h-16 mb-4">
                </div>

                <!-- Contact -->
                <div>
                    <h5 class="text-xl font-bold mb-4">Contact</h5>
                    <ul class="space-y-1 text-gray-300 text-sm">
                        <li>{{ $companyName }}</li>
                        <li><a href="mailto:{{ $email }}" class="hover:text-white transition-colors">{{ $email }}</a></li>
                        <li>{{ $companyAddress }}</li>
                        <li><a href="{{ $companyUrl }}" target="_blank" class="hover:text-white transition-colors">{{ $companyUrl }}</a></li>
                    </ul>
                </div>

                <!-- Social Links -->
                <div>
                    <h5 class="text-xl font-bold mb-4">Follow Us</h5>
                    <div class="flex gap-4">
                        @if($instagramUrl)
                        <a href="{{ $instagramUrl }}" target="_blank" class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center hover:bg-opacity-30 transition-all" aria-label="Instagram">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        @endif
                        @if($discordUrl)
                        <a href="{{ $discordUrl }}" target="_blank" class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center hover:bg-opacity-30 transition-all" aria-label="Discord">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C2.451 6.018 1.73 7.713 1.412 9.432a.082.082 0 0 0-.031.084 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.007-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928-1.793 8.18-1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.128 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0-.032-.084c-.318-1.72-1.04-3.415-2.234-5.036a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/>
                            </svg>
                        </a>
                        @endif
                        @if($githubUrl)
                        <a href="{{ $githubUrl }}" target="_blank" class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center hover:bg-opacity-30 transition-all" aria-label="GitHub">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                            </svg>
                        </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Copyright -->
    <div class="bg-gray-900 py-4">
        <div class="container mx-auto px-4 text-center text-gray-400 text-sm">
            <p>Copyright @php echo date('Y'); @endphp {{ $companyName }} | <a href="/imprint" class="hover:text-white transition-colors">Imprint</a></p>
        </div>
    </div>
</footer>

