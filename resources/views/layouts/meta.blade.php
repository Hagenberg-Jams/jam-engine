{{-- Render the dynamic page meta tags --}}
{{ $page->metadata() }}

{{-- Render the global and config defined meta tags (excluding invalid rel="sitemap" link) --}}
@php
    $globalMeta = \Hyde\Facades\Site::metadata()->toHtml();
    $globalMeta = preg_replace('/<link[^>]*\brel=["\']sitemap["\'][^>]*\/?>\s*/i', '', $globalMeta);
    echo $globalMeta;
@endphp

{{-- Add any extra tags to include in the <head> section --}}
@stack('meta')
