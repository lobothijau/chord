@extends('layouts.app')

@section('title', 'Songs')

@section('content')
    <form method="GET" action="{{ route('songs.index') }}" class="search-form">
        <input type="search" name="q" id="search-input" value="{{ $q }}"
               placeholder="Search title or artist&hellip;" autofocus autocomplete="off">
    </form>

    <div id="song-list-container">
        @include('songs._list')
    </div>
@endsection

@push('scripts')
    <script defer src="@assetv('js/search.js')"></script>
@endpush
