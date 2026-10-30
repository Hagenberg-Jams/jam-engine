<!-- Controls -->
        @if(!empty($controlsText))
            <div class="mb-12">
                <h2 class="text-3xl font-bold mb-6 dark:text-white">Controls</h2>
                <div class="bg-gray-100 dark:bg-gray-800 rounded-lg p-6">
                    <pre class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap font-sans">{{ $controlsText }}</pre>
                </div>
            </div>
        @endif
