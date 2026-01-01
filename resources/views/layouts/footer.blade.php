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
        <div class="bg-gray-800 relative">
        <div class="container mx-auto px-4 py-16 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Logo -->
                <div>
                    <img src="/media/{{ $logoLight }}" alt="{{ config('hyde.name', 'Hagenberg Game Jam') }}" class="h-24">
                </div>

                <!-- Contact -->
                <div>
                    <h5 class="text-xl font-bold mb-4">Contact</h5>
                    <ul class="space-y-1 text-gray-300 text-sm">
                        <li><a href="mailto:{{ $email }}" class="hover:text-white transition-colors">{{ $email }}</a></li>
                        <li>{{ $companyName }}</li>
                        <li>{{ $companyAddress }}</li>
                        <li><a href="{{ $companyUrl }}" target="_blank" class="hover:text-white transition-colors">{{ $companyUrl }}</a></li>
                    </ul>
                </div>

                <!-- Social Links -->
                <div>
                    <h5 class="text-xl font-bold mb-4">Follow Us</h5>
                    <div class="flex gap-4">
                        @if($instagramUrl)
                        <a href="{{ $instagramUrl }}" target="_blank" class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors" aria-label="Instagram">
                            <svg class="w-6 h-6" viewBox="0 0 24 24">
                                <path fill="#000000" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                            </svg>
                        </a>
                        @endif
                        @if($discordUrl)
                        <a href="{{ $discordUrl }}" target="_blank" class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors" aria-label="Discord">
                            <svg class="w-6 h-6" viewBox="0 0 512 512">
                                <path fill="#000000" d="M433.43,93.222c-32.633-14.973-67.627-26.005-104.216-32.324c-0.666-0.122-1.332,0.183-1.675,0.792   c-4.501,8.005-9.486,18.447-12.977,26.655c-39.353-5.892-78.505-5.892-117.051,0c-3.492-8.39-8.658-18.65-13.179-26.655   c-0.343-0.589-1.009-0.894-1.675-0.792c-36.568,6.298-71.562,17.33-104.216,32.324c-0.283,0.122-0.525,0.325-0.686,0.589   c-66.376,99.165-84.56,195.893-75.64,291.421c0.04,0.467,0.303,0.914,0.666,1.198c43.793,32.161,86.215,51.685,127.848,64.627   c0.666,0.203,1.372-0.04,1.796-0.589c9.848-13.449,18.627-27.63,26.154-42.543c0.444-0.873,0.02-1.909-0.888-2.255   c-13.925-5.282-27.184-11.723-39.939-19.036c-1.009-0.589-1.09-2.032-0.161-2.723c2.684-2.011,5.369-4.104,7.932-6.217   c0.464-0.386,1.11-0.467,1.655-0.224c83.792,38.257,174.507,38.257,257.31,0c0.545-0.264,1.191-0.182,1.675,0.203   c2.564,2.113,5.248,4.226,7.952,6.237c0.928,0.691,0.867,2.134-0.141,2.723c-12.755,7.456-26.014,13.754-39.959,19.016   c-0.908,0.345-1.312,1.402-0.867,2.275c7.689,14.892,16.468,29.073,26.134,42.523c0.404,0.569,1.13,0.813,1.796,0.609   c41.835-12.941,84.257-32.466,128.05-64.627c0.384-0.284,0.626-0.711,0.666-1.178c10.676-110.441-17.881-206.376-75.7-291.421   C433.954,93.547,433.712,93.344,433.43,93.222z M171.094,327.065c-25.227,0-46.014-23.16-46.014-51.604   s20.383-51.604,46.014-51.604c25.831,0,46.417,23.364,46.013,51.604C217.107,303.905,196.723,327.065,171.094,327.065z    M341.221,327.065c-25.226,0-46.013-23.16-46.013-51.604s20.383-51.604,46.013-51.604c25.832,0,46.417,23.364,46.014,51.604   C387.235,303.905,367.054,327.065,341.221,327.065z"/>
                            </svg>
                        </a>
                        @endif
                        @if($githubUrl)
                        <a href="{{ $githubUrl }}" target="_blank" class="w-10 h-10 bg-white rounded-full flex items-center justify-center hover:bg-gray-200 transition-colors" aria-label="GitHub">
                            <svg class="w-6 h-6" viewBox="0 0 24 24">
                                <path fill="#000000" d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
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

