<?php

namespace Tests\Feature;

use App\Models\Song;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportApiTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = [
        'title' => 'Encore',
        'artist' => 'GOT7',
        'content' => "Am   F   C   G\nSome lyric line here",
        'source_url' => 'https://www.chordtela.com/2021/02/got7-encore.html',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.import_token' => 'test-token-123']);
    }

    private function apiPost(array $payload, ?string $token = 'test-token-123')
    {
        return $this->postJson(route('api.import'), $payload, $token ? ['Authorization' => "Bearer {$token}"] : []);
    }

    public function test_rejects_missing_or_wrong_token(): void
    {
        $this->apiPost(self::PAYLOAD, null)->assertForbidden();
        $this->apiPost(self::PAYLOAD, 'wrong')->assertForbidden();
        $this->assertDatabaseCount('songs', 0);
    }

    public function test_rejects_when_no_token_configured(): void
    {
        config(['services.import_token' => null]);

        $this->apiPost(self::PAYLOAD)->assertForbidden();
    }

    public function test_creates_song_with_detected_key(): void
    {
        $this->apiPost(self::PAYLOAD)
            ->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('key', 'G');

        $this->assertDatabaseHas('songs', ['title' => 'Encore', 'artist' => 'GOT7']);
    }

    public function test_skips_duplicate_source_url(): void
    {
        $this->apiPost(self::PAYLOAD)->assertCreated();

        $this->apiPost(self::PAYLOAD)
            ->assertOk()
            ->assertJsonPath('status', 'skipped');

        $this->assertDatabaseCount('songs', 1);
    }

    public function test_dedups_on_title_and_artist_without_source_url(): void
    {
        $payload = collect(self::PAYLOAD)->except('source_url')->all();

        $this->apiPost($payload)->assertCreated();
        $this->apiPost($payload)->assertOk()->assertJsonPath('status', 'skipped');

        $this->assertDatabaseCount('songs', 1);
    }

    public function test_validates_payload(): void
    {
        $this->apiPost(['title' => 'No content'])->assertStatus(422);
    }

    public function test_csrf_not_required(): void
    {
        // postJson goes through the web middleware stack; without the CSRF
        // exemption this would 419 before reaching the controller.
        Song::query()->delete();

        $this->apiPost(self::PAYLOAD)->assertCreated();
    }
}
