<?php

namespace Tests\Feature;

use App\Services\SongImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SongImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_largest_pre_block_from_generic_chord_site(): void
    {
        $html = '<html><head>'
            .'<meta property="og:title" content="Chord GOT7 - Encore | Chordtela">'
            .'<title>ignored</title></head><body>'
            .'<pre>short block</pre>'
            ."<pre>Intro : C Em Am G\n\nC        Em\nEvery day I dream of you\nAm              G\nAnd every night you're in my mind</pre>"
            .'</body></html>';

        Http::fake(['www.chordtela.com/*' => Http::response($html)]);

        $result = app(SongImporter::class)->import('https://www.chordtela.com/2021/02/got7-encore.html');

        $this->assertSame('GOT7 - Encore', $result['title']);
        $this->assertStringContainsString('Every day I dream of you', $result['content']);
        $this->assertStringContainsString('Intro : C Em Am G', $result['content']);
    }

    public function test_imports_telabox_markup_from_newer_chordtela_pages(): void
    {
        $html = '<html><head>'
            .'<meta property="og:title" content="Kunci Gitar U.K&#039;s - Cinta Itu Buta Chord Dasar ©ChordTela.com">'
            .'</head><body><div class="telabox">'
            .'Intro :&nbsp;<a class="tbi-tooltip">Am<span class="custom tbi-Am"></span></a>'
            .'&nbsp;<a class="tbi-tooltip">D<span class="custom tbi-D"></span></a><br />'
            ."\n".'<a class="tbi-tooltip">Em<span class="custom tbi-Em"></span></a><br />'
            ."\n".'&nbsp; penantian ini hanya untuk luka..</div></body></html>';

        Http::fake(['www.chordtela.com/*' => Http::response($html)]);

        $result = app(SongImporter::class)->import('https://www.chordtela.com/2016/05/uks.html');

        $this->assertSame("U.K's - Cinta Itu Buta", $result['title']);
        $this->assertStringContainsString('Intro', $result['content']);
        $this->assertStringContainsString('penantian ini hanya untuk luka..', $result['content']);
        $this->assertStringNotContainsString('<a', $result['content']);
        $this->assertStringNotContainsString('tbi-tooltip', $result['content']);
    }

    public function test_generic_site_without_pre_fails_with_paste_hint(): void
    {
        Http::fake(['*' => Http::response('<html><body><p>nothing here</p></body></html>')]);

        $this->postJson(route('songs.fetch-url'), ['url' => 'https://example.com/some-song'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'No chord block found on the page. Copy the chords from the page and paste them below instead — or use the bookmarklet (see the tip under the URL field).']);
    }

    public function test_blocked_site_fails_with_paste_hint(): void
    {
        Http::fake(['*' => Http::response('Attention Required! Cloudflare', 403)]);

        $this->postJson(route('songs.fetch-url'), ['url' => 'https://www.chordtela.com/blocked.html'])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'blocked'));
    }

    public function test_create_form_prefills_from_bookmarklet_query_params(): void
    {
        $this->get(route('songs.create', [
            'title' => 'Chord GOT7 - Encore | Chordtela',
            'content' => "C   Em\nSome lyric",
            'source_url' => 'https://www.chordtela.com/2021/02/got7-encore.html',
        ]))
            ->assertOk()
            ->assertSee('value="GOT7 - Encore"', false)
            ->assertSee("C   Em\nSome lyric")
            ->assertSee('https://www.chordtela.com/2021/02/got7-encore.html');
    }

    public function test_clean_title_strips_site_noise(): void
    {
        $this->assertSame('GOT7 - Encore', SongImporter::cleanTitle('Chord GOT7 - Encore | Chordtela'));
        $this->assertSame('Peterpan - Semua Tentang Kita', SongImporter::cleanTitle('Kunci Gitar Peterpan - Semua Tentang Kita | Chordtela.com'));
        $this->assertSame('Wonderwall by Oasis', SongImporter::cleanTitle('Wonderwall by Oasis | Ultimate Guitar'));
        $this->assertSame('GOT7 - ENCORE', SongImporter::cleanTitle('GOT7 - ENCORE Chord Dasar ©ChordTela.com'));
    }

    public function test_detects_capo_from_generic_content(): void
    {
        Http::fake(['*' => Http::response('<html><title>Song</title><body><pre>Capo fret 2'."\n\nC  Em\nlyric line here</pre></body></html>")]);

        $result = app(SongImporter::class)->import('https://www.chordtela.com/x.html');

        $this->assertSame(2, $result['capo']);
    }
}
