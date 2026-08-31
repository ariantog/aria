@extends('layouts.app')

@section('title', 'Checklist Saya')

@section('content')
@php
$breadcrumbs = [
    ['title' => 'Checklist Saya', 'href' => route('my-checklist.index')],
];
@endphp

<div class="flex flex-col gap-4 p-3 sm:p-4">
    @include('partials.staff-checklist', [
        'checklist' => $checklist,
        'showFullPageLink' => false,
    ])
</div>
@endsection
