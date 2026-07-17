@extends('docent::layout')

@section('content')
    <x-docent::hero
        :title="$title"
        :description="$description"
        :badge="$heroBadge ?? null"
        :cta="$heroCta ?? []"
        :search="($heroSearch ?? false) && $searchEnabled" />

    <div class="docent-prose docent-landing-body mx-auto mt-12 max-w-5xl">
        {!! $html !!}
    </div>
@endsection
