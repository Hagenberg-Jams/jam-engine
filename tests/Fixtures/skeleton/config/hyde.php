<?php

return [
    'name' => env('SITE_NAME', 'Test Jam'),
    'url' => 'https://example.test',
    'pretty_urls' => false,
    'generate_sitemap' => true,
    'rss' => ['enabled' => false],
    'load_app_styles_from_cdn' => false,
    'navigation' => ['exclude' => ['404']],
];
