@if ($errors->any())
    <div class="alert alert-error">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ $action }}" class="song-form">
    @csrf
    @if ($song->exists)
        @method('PUT')
    @endif

    @php
        // Grabs selection, else the largest chord block: <pre> (most sites) or
        // div.telabox (chordtela). Title/source travel as query params (short,
        // server cleans the title); the sheet itself goes via postMessage —
        // URL length limits and clipboard permissions made the old paths flaky.
        $bookmarklet = "javascript:(function(){var s=window.getSelection().toString();"
            ."var p=s||[].map.call(document.querySelectorAll('pre,.telabox'),function(e){return e.innerText}).sort(function(a,b){return b.length-a.length})[0]||'';"
            ."if(!p.trim()){alert('No chords found. Select the chord text first, then click the bookmarklet again.');return}"
            ."var w=window.open('".url('/songs/create')."?title='+encodeURIComponent(document.title)+'&source_url='+encodeURIComponent(location.href));"
            ."if(!w){alert('Popup blocked. Allow popups for this site, then try again.');return}"
            ."var m={chords:1,content:p},n=0;"
            ."var t=setInterval(function(){if(n++>50){clearInterval(t)}try{w.postMessage(m,'*')}catch(e){}},400);"
            ."window.addEventListener('message',function(e){if(e.data==='chords:ack')clearInterval(t)});"
            ."})()";
    @endphp

    <div class="form-row url-row">
        <label for="fetch-url-input">Import from URL <small>(works when the site doesn't block robots)</small></label>
        <div class="url-fetch">
            <input type="url" id="fetch-url-input" placeholder="https://tabs.ultimate-guitar.com/... or https://www.chordtela.com/..."
                   value="{{ old('source_url', $song->source_url) }}">
            <button type="button" class="btn" id="fetch-url-btn">Fetch</button>
        </div>
        <p class="fetch-error" id="fetch-error" hidden></p>
        <details class="bookmarklet-tip">
            <summary>Site blocked? Grab chords straight from your browser</summary>
            <p>
                Drag this to your bookmarks bar: <a href="{{ $bookmarklet }}" class="btn bookmarklet">&#119070; Save to Chords</a><br>
                Then on any chord page, click it — the chords and title land here automatically.
                Sites like Chordtela block server fetching, but your browser already has the page.
            </p>
        </details>
    </div>

    <div class="form-grid">
        <div class="form-row">
            <label for="title">Title *</label>
            <input type="text" id="title" name="title" required value="{{ old('title', $song->title) }}">
        </div>
        <div class="form-row">
            <label for="artist">Artist</label>
            <input type="text" id="artist" name="artist" value="{{ old('artist', $song->artist) }}">
        </div>
        <div class="form-row">
            <label for="original_key">Key <small>(blank = auto-detect)</small></label>
            <input type="text" id="original_key" name="original_key" size="4" placeholder="e.g. G, F#m"
                   value="{{ old('original_key', $song->original_key) }}">
        </div>
        <div class="form-row">
            <label for="capo">Capo</label>
            <input type="number" id="capo" name="capo" min="0" max="12" value="{{ old('capo', $song->capo) }}">
        </div>
    </div>

    <input type="hidden" name="source_url" id="source_url" value="{{ old('source_url', $song->source_url) }}">

    <div class="editor-split">
        <div class="form-row">
            <label for="content">Chord sheet * <small>(chords on their own line above lyrics)</small></label>
            <textarea id="content" name="content" rows="24" spellcheck="false"
                      placeholder="[Verse 1]&#10;Em            G&#10;Today is gonna be the day..." required>{{ old('content', $song->content) }}</textarea>
        </div>
        <div class="form-row">
            <label>Preview</label>
            <div class="sheet preview-pane" id="preview-pane"><span class="muted">Paste a sheet to preview&hellip;</span></div>
        </div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">{{ $song->exists ? 'Save changes' : 'Save song' }}</button>
        <a href="{{ $song->exists ? route('songs.show', $song) : route('songs.index') }}" class="btn">Cancel</a>
    </div>
</form>

@push('scripts')
    <script defer src="@assetv('js/form.js')"></script>
@endpush
