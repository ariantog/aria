@extends('layouts.app')

@section('title', 'Piutang Usaha')

@section('content')
    @include('reports.partials.aging-table')
@endsection
