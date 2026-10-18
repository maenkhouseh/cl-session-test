@extends('redirect._layout')
@section('title', config('app.name'))
@section('content')
    <h1>{{ config('app.name') }}</h1>
    <p>This domain serves short links.</p>
@endsection
