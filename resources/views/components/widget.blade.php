@if(\STS\Docent\Facades\Docent::config('widget.enabled', false))
    @php($widget = app(\STS\Docent\DocentManager::class)->widgetConfig())
    <script>window.Docent=window.Docent||function(){(window.Docent.q=window.Docent.q||[]).push(arguments)};</script>
    <script type="application/json" data-docent-widget-config>{!! json_encode($widget, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script defer src="{{ app(\STS\Docent\DocentManager::class)->asset('docent-widget.js') }}" data-docent-widget-runtime></script>
@endif
