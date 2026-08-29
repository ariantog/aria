@extends('layouts.app')

@section('title', 'Hutang Usaha')

@section('content')
    @include('reports.partials.aging-table')
@endsection
