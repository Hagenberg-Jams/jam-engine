@php
    /** @var \Hyde\Framework\Features\Navigation\NavigationItem $item */
    $url = (string) $item;
    // Ensure absolute URL (starts with /)
    if (!str_starts_with($url, '/')) {
        $url = '/' . $url;
    }
    $isActive = $item->isActive();
@endphp

<a href="{{ $url }}"
   class="block my-2 md:my-0 md:inline-block py-1 text-gray-700 hover:text-gray-900 dark:text-gray-100 {{ $isActive ? 'font-bold' : '' }}"
   @if($isActive) aria-current="page" @endif>
    {{ $item->getLabel() }}
</a>
