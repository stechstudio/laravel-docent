@props(['site' => null])
@php($docent = \STS\Docent\Facades\Docent::site($site))
@if($docent->config('widget.enabled', false))
    @php($widget = $docent->widgetConfig())
    <script>window.Docent=window.Docent||function(){(window.Docent.q=window.Docent.q||[]).push(arguments)};</script>
    <script type="application/json" data-docent-widget-config>{!! json_encode($widget, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script defer src="{{ $docent->asset('docent-widget.js') }}" data-docent-widget-runtime></script>
@endif
