<!-- Team Members -->
        @if(isset($team['members']) && count($team['members']) > 0)
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 dark:text-white">Team Members</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($team['members'] as $member)
                        @php
                            $personSlug = \Illuminate\Support\Str::slug(trim(preg_replace('/\s+/', ' ', $member)));
                        @endphp
                        <div class="bg-gray-200 dark:bg-gray-800 rounded-lg p-4 text-center">
                            <a href="/person/{{ $personSlug }}" 
                               class="font-semibold dark:text-white hover:text-primary-600 dark:hover:text-primary-400 transition-colors">
                                {{ $member }}
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
