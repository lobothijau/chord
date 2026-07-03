<?php

namespace Tests\Feature;

use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSongPage(string $title): string
    {
        return '<html><head><meta property="og:title" content="Chord '.$title.' | Chordtela"></head>'
            .'<body><pre>Am   F   C   G'."\n".'Lyrics for '.$title.'</pre></body></html>';
    }

    public function test_imports_single_url_locally(): void
    {
        Http::fake(['www.chordtela.com/*' => Http::response($this->fakeSongPage('Band - Song One'))]);

        $this->artisan('chords:import', ['urls' => ['https://www.chordtela.com/2021/01/song-one.html']])
            ->assertSuccessful();

        $this->assertDatabaseHas('songs', ['title' => 'Song One', 'artist' => 'Band']);
        $this->assertSame('G', Song::first()->original_key);
    }

    public function test_skips_already_imported_url(): void
    {
        Song::create([
            'title' => 'Existing',
            'content' => "Am  F\nline",
            'source_url' => 'https://www.chordtela.com/2021/01/song-one.html',
        ]);

        Http::fake(['www.chordtela.com/*' => Http::response($this->fakeSongPage('Band - Song One'))]);

        $this->artisan('chords:import', ['urls' => ['https://www.chordtela.com/2021/01/song-one.html']])
            ->expectsOutputToContain('skipped')
            ->assertSuccessful();

        $this->assertDatabaseCount('songs', 1);
    }

    public function test_list_mode_discovers_and_imports_song_links(): void
    {
        $listing = '<html><body>'
            .'<a href="https://www.chordtela.com/2021/01/song-one.html">One</a>'
            .'<a href="https://www.chordtela.com/2021/02/song-two.html">Two</a>'
            .'<a href="https://www.chordtela.com/2021/02/song-two.html">Two dup</a>'
            .'<a href="https://www.chordtela.com/p/about.html">About page</a>'
            .'<a href="https://other-site.com/2021/01/foreign.html">Foreign</a>'
            .'</body></html>';

        Http::fake([
            'www.chordtela.com/artist/band' => Http::response($listing),
            'www.chordtela.com/2021/01/song-one.html' => Http::response($this->fakeSongPage('Band - Song One')),
            'www.chordtela.com/2021/02/song-two.html' => Http::response($this->fakeSongPage('Band - Song Two')),
        ]);

        $this->artisan('chords:import', ['--list' => 'https://www.chordtela.com/artist/band'])
            ->expectsOutputToContain('2 song links found')
            ->assertSuccessful();

        $this->assertDatabaseCount('songs', 2);
    }

    public function test_dry_run_saves_nothing(): void
    {
        Http::fake(['www.chordtela.com/*' => Http::response($this->fakeSongPage('Band - Song One'))]);

        $this->artisan('chords:import', [
            'urls' => ['https://www.chordtela.com/2021/01/song-one.html'],
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('songs', 0);
    }

    public function test_remote_pushes_to_api(): void
    {
        Http::fake([
            'www.chordtela.com/*' => Http::response($this->fakeSongPage('Band - Song One')),
            'chord.example.com/api/import' => Http::response(['status' => 'created', 'id' => 7, 'title' => 'Band - Song One'], 201),
        ]);

        config(['services.chords_remote' => [
            'url' => 'https://chord.example.com',
            'token' => 'remote-token',
            'basic' => 'bagus:secret',
        ]]);

        $this->artisan('chords:import', [
            'urls' => ['https://www.chordtela.com/2021/01/song-one.html'],
            '--remote' => true,
        ])->assertSuccessful();

        $this->assertDatabaseCount('songs', 0); // saved remotely, not locally

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/api/import')
                && $request->hasHeader('X-Import-Token', 'remote-token')
                && $request->hasHeader('Authorization') // basic auth intact alongside token
                && $request['title'] === 'Song One'
                && $request['artist'] === 'Band';
        });
    }

    public function test_all_failures_exit_nonzero(): void
    {
        Http::fake(['*' => Http::response('blocked', 403)]);

        $this->artisan('chords:import', ['urls' => ['https://www.chordtela.com/2021/01/song-one.html']])
            ->assertFailed();
    }
}
