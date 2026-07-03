@extends('layouts.app')

@section('title', 'Add song')

@section('content')
    <h1>Add song</h1>
    @include('songs._form', ['action' => route('songs.store')])
@endsection
