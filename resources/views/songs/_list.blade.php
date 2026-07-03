@if ($songs->isEmpty())
    <div class="empty-state">
        @if ($q !== '')
            <p>No songs match &ldquo;{{ $q }}&rdquo;.</p>
        @else
            <p>No songs yet.</p>
            <p><a href="{{ route('songs.create') }}">Add your first song</a> — paste a chord sheet from any site.</p>
        @endif
    </div>
@else
    <ul class="song-list">
        @foreach ($songs as $song)
            <li>
                <a href="{{ route('songs.show', $song) }}" class="song-link">
                    <span class="song-title">{{ $song->title }}</span>
                    @if ($song->artist)
                        <span class="song-artist">{{ $song->artist }}</span>
                    @endif
                </a>
                @if ($song->original_key)
                    <span class="badge">{{ $song->original_key }}</span>
                @endif
            </li>
        @endforeach
    </ul>
@endif
