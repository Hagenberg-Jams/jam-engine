<!-- Description -->
        <div class="prose prose-lg dark:prose-invert max-w-none mb-12">
            <h2 class="text-3xl font-bold mb-6 dark:text-white">{{ config('jam.labels.about_project') }}</h2>
            <div class="text-gray-700 dark:text-gray-300">
                @php
                    // Split by double line breaks (paragraphs)
                    $paragraphs = preg_split('/\n\s*\n/', trim($description));
                @endphp
                @foreach($paragraphs as $paragraph)
                    @php
                        // Convert single line breaks to markdown hard breaks (two spaces + newline)
                        // This allows markdown to convert them to <br> while also processing links
                        // Uses lookahead (?=[^\n]) to avoid consuming the next character, so consecutive
                        // newlines (3+ lines) are all converted, not just the first one.
                        $paragraphWithHardBreaks = preg_replace('/([^\n])\n(?=[^\n])/', '$1  ' . "\n", trim($paragraph));
                        // Process markdown (converts links and hard breaks)
                        $htmlContent = \Illuminate\Support\Str::markdown($paragraphWithHardBreaks);
                        // Remove wrapping <p> tag if markdown added one (we add our own)
                        $htmlContent = preg_replace('/^<p>(.*)<\/p>$/s', '$1', $htmlContent);
                    @endphp
                    <p>{!! $htmlContent !!}</p>
                @endforeach
            </div>
        </div>
