@extends('docent::layout')

@section('topbar-nav')
    <span data-test="custom-topbar-nav">CustomNav</span>
@endsection

@section('topbar-actions')
@endsection

@section('content')
    <h1>{{ $title }}</h1>
    {!! $html !!}
@endsection
