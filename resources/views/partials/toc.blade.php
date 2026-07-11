@if(!empty($toc))
    <span class="docent-toc-title">On this page</span>
    <ul class="docent-toc-list">
        @foreach($toc as $entry)
            @include('docent::partials.toc-entry', ['entry' => $entry])
        @endforeach
    </ul>
@endif
