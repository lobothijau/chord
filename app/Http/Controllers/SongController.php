<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Services\ChordParser;
use App\Services\ChordSheetRenderer;
use App\Services\ImportException;
use App\Services\KeyDetector;
use App\Services\SongImporter;
use Illuminate\Http\Request;

class SongController extends Controller
{
    public function __construct(
        private ChordParser $parser,
        private ChordSheetRenderer $renderer,
        private KeyDetector $keyDetector,
    ) {
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $songs = Song::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('artist', 'like', "%{$q}%")))
            ->orderBy('artist')
            ->orderBy('title')
            ->get();

        // Debounced live search fetches just the list fragment.
        $view = $request->ajax() ? 'songs._list' : 'songs.index';

        return view($view, ['songs' => $songs, 'q' => $q]);
    }

    public function create(Request $request)
    {
        // Bookmarklet lands here with ?title=&content=&source_url= prefills.
        $song = new Song($request->only(['title', 'artist', 'content', 'source_url']));

        if ($song->title) {
            if ($song->artist) {
                $song->title = SongImporter::cleanTitle($song->title);
            } else {
                [$song->artist, $song->title] = SongImporter::parsePageTitle($song->title);
            }
        }

        return view('songs.create', ['song' => $song]);
    }

    public function store(Request $request)
    {
        $song = Song::create($this->validated($request));

        return redirect()->route('songs.show', $song);
    }

    public function show(Song $song)
    {
        return view('songs.show', [
            'song' => $song,
            'sheet' => $this->renderer->render($song->content),
        ]);
    }

    public function edit(Song $song)
    {
        return view('songs.edit', ['song' => $song]);
    }

    public function update(Request $request, Song $song)
    {
        $song->update($this->validated($request));

        return redirect()->route('songs.show', $song);
    }

    public function destroy(Song $song)
    {
        $song->delete();

        return redirect()->route('songs.index');
    }

    public function preview(Request $request)
    {
        $content = $this->parser->normalize((string) $request->input('content', ''));

        return response($this->renderer->render($content));
    }

    public function fetchUrl(Request $request, SongImporter $importer)
    {
        $request->validate(['url' => ['required', 'url']]);

        try {
            return response()->json($importer->import($request->input('url')));
        } catch (ImportException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Token-authenticated endpoint for the `chords:import --remote` command.
     * Dedups on source_url, else on title+artist.
     */
    public function apiImport(Request $request)
    {
        // Custom header, not Authorization: nginx basic auth owns that one.
        $token = config('services.import_token');
        $given = (string) ($request->header('X-Import-Token') ?: $request->bearerToken());
        if (! $token || ! hash_equals($token, $given)) {
            abort(403, 'Invalid import token.');
        }

        $data = $this->validated($request);

        $existing = Song::query()
            ->when($data['source_url'] ?? null,
                fn ($q) => $q->where('source_url', $data['source_url']),
                fn ($q) => $q->where('title', $data['title'])
                    ->where('artist', $data['artist'] ?? null))
            ->first();

        if ($existing) {
            return response()->json(['status' => 'skipped', 'id' => $existing->id, 'title' => $existing->title]);
        }

        $song = Song::create($data);

        return response()->json([
            'status' => 'created',
            'id' => $song->id,
            'title' => $song->title,
            'key' => $song->original_key,
        ], 201);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'original_key' => ['nullable', 'string', 'regex:/^[A-G][#b]?m?$/'],
            'capo' => ['nullable', 'integer', 'min:0', 'max:12'],
            'source_url' => ['nullable', 'url', 'max:255'],
        ]);

        $data['content'] = $this->parser->normalize($data['content']);

        if (empty($data['original_key'])) {
            $data['original_key'] = $this->keyDetector->detectFromContent($data['content']);
        }

        return $data;
    }
}
