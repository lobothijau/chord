@extends('layouts.app')

@section('title', $song->title.($song->artist ? ' – '.$song->artist : ''))

@section('content')
    <div class="player" id="player"
         data-song-id="{{ $song->id }}"
         data-key="{{ $song->original_key }}">

        <div class="song-header">
            <div>
                <h1>{{ $song->title }}</h1>
                @if ($song->artist)
                    <p class="artist">{{ $song->artist }}</p>
                @endif
            </div>
            <div class="song-actions">
                <a href="{{ route('songs.edit', $song) }}" class="btn">Edit</a>
                <form method="POST" action="{{ route('songs.destroy', $song) }}"
                      onsubmit="return confirm('Delete “{{ $song->title }}”?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>

        <div class="toolbar" id="toolbar">
            <div class="toolbar-group">
                <span class="toolbar-label">Transpose</span>
                <button type="button" class="btn" id="transpose-down" title="Down a semitone ( [ )">&minus;</button>
                <button type="button" class="btn" id="transpose-reset" title="Reset to original key">
                    <span id="key-badge">{{ $song->original_key ?: '—' }}</span>
                </button>
                <button type="button" class="btn" id="transpose-up" title="Up a semitone ( ] )">+</button>
                @if ($song->capo)
                    <span class="badge">Capo {{ $song->capo }}</span>
                @endif
            </div>
            <div class="toolbar-group">
                <span class="toolbar-label">Scroll</span>
                <button type="button" class="btn" id="scroll-toggle" title="Play/pause auto-scroll (Space)">&#9654;</button>
                <input type="range" id="scroll-speed" min="5" max="120" step="5" title="Scroll speed">
            </div>
            <div class="toolbar-group">
                <span class="toolbar-label">Text</span>
                <button type="button" class="btn" id="font-down" title="Smaller text">A&minus;</button>
                <button type="button" class="btn" id="font-up" title="Bigger text">A+</button>
            </div>
        </div>

        <div class="sheet" id="sheet">{!! $sheet !!}</div>
    </div>

    <div id="chord-popover" hidden></div>
@endsection

@push('scripts')
    <script defer src="@assetv('js/music.js')"></script>
    <script defer src="@assetv('js/chord-data.js')"></script>
    <script defer src="@assetv('js/diagrams.js')"></script>
    <script defer src="@assetv('js/autoscroll.js')"></script>
    <script defer src="@assetv('js/player.js')"></script>
@endpush
