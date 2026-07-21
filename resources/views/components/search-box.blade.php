{{-- @internal Docent component. Not part of the public API; props may change. --}}
{{--
    A search box that opens the search-and-ask dialog. `size="lg"` is the
    hero-scale variant; the default suits inline placement on any page.
--}}
@props(['docent', 'size' => 'md', 'placeholder' => null])

@php
    $placeholder ??= $docent->config('ai.enabled', false)
        ? __('docent::ui.search.docs_or_ask_placeholder')
        : __('docent::ui.search.docs_placeholder');
@endphp

<button type="button"
        onclick="window.dispatchEvent(new CustomEvent('docent:search-open'))"
        aria-label="{{ __('docent::ui.search.label') }}"
        {{ $attributes->merge(['class' => 'docent-search-box'.($size === 'lg' ? ' docent-search-box-lg' : '')]) }}>
    <svg viewBox="0 0 24 24" width="{{ $size === 'lg' ? 20 : 16 }}" height="{{ $size === 'lg' ? 20 : 16 }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <span class="docent-search-box-placeholder">{{ $placeholder }}</span>
    <kbd class="docent-search-box-kbd" data-docent-kbd>⌘K</kbd>
</button>
