@extends('layouts.app')

@section('title', 'Edit: '.$song->title)

@section('content')
    <h1>Edit song</h1>
    @include('songs._form', ['action' => route('songs.update', $song)])
@endsection
