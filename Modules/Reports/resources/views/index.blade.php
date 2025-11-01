@extends('reports::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('reports.name') !!}</p>
@endsection
