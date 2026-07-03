<?php

namespace Tests\Feature;

use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SongFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SHEET = "[Verse]\nAm   F   C   G\nSome lyrics under the chords";

    public function test_create_song_and_view_rendered_sheet(): void
    {
        $response = $this->post(route('songs.store'), [
            'title' => 'Test Song',
            'artist' => 'Test Artist',
            'content' => self::SHEET,
        ]);

        $song = Song::first();
        $response->assertRedirect(route('songs.show', $song));

        $this->assertSame('G', $song->original_key); // auto-detected: last chord (G) carries the cadence bonus

        $this->get(route('songs.show', $song))
            ->assertOk()
            ->assertSee('data-chord="Am"', false)
            ->assertSee('data-chord="F"', false)
            ->assertSee('class="line section"', false);
    }

    public function test_search_filters_songs(): void
    {
        Song::create(['title' => 'Wonderwall', 'artist' => 'Oasis', 'content' => self::SHEET]);
        Song::create(['title' => 'Yesterday', 'artist' => 'The Beatles', 'content' => self::SHEET]);

        $this->get(route('songs.index', ['q' => 'oasis']))
            ->assertOk()
            ->assertSee('Wonderwall')
            ->assertDontSee('Yesterday');
    }

    public function test_ajax_search_returns_list_fragment_only(): void
    {
        Song::create(['title' => 'Wonderwall', 'artist' => 'Oasis', 'content' => self::SHEET]);

        $response = $this->get(
            route('songs.index', ['q' => 'wonder']),
            ['X-Requested-With' => 'XMLHttpRequest'],
        );

        $response->assertOk()->assertSee('Wonderwall');
        $this->assertStringNotContainsString('<html', $response->getContent());
        $this->assertStringNotContainsString('search-form', $response->getContent());
    }

    public function test_update_song(): void
    {
        $song = Song::create(['title' => 'Old', 'content' => self::SHEET]);

        $this->put(route('songs.update', $song), [
            'title' => 'New Title',
            'content' => self::SHEET,
            'original_key' => 'Em',
        ])->assertRedirect(route('songs.show', $song));

        $this->assertSame('New Title', $song->fresh()->title);
        $this->assertSame('Em', $song->fresh()->original_key);
    }

    public function test_delete_song(): void
    {
        $song = Song::create(['title' => 'Gone', 'content' => self::SHEET]);

        $this->delete(route('songs.destroy', $song))->assertRedirect(route('songs.index'));

        $this->assertDatabaseCount('songs', 0);
    }

    public function test_preview_returns_rendered_html(): void
    {
        $this->post(route('songs.preview'), ['content' => "Am  F\nHello world"])
            ->assertOk()
            ->assertSee('data-chord="Am"', false)
            ->assertSee('class="line lyric"', false);
    }

    public function test_rejects_invalid_key(): void
    {
        $this->post(route('songs.store'), [
            'title' => 'Bad Key',
            'content' => self::SHEET,
            'original_key' => 'X#',
        ])->assertSessionHasErrors('original_key');
    }
}
