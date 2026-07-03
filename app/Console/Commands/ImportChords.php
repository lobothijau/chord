<?php

namespace App\Console\Commands;

use App\Models\Song;
use App\Services\ChordParser;
use App\Services\ImportException;
use App\Services\KeyDetector;
use App\Services\SongImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportChords extends Command
{
    protected $signature = 'chords:import
        {urls?* : chord page URLs to import}
        {--list= : listing/band page — import every song link found on it}
        {--remote : push to the production site instead of the local database}
        {--dry-run : fetch and parse, but save nothing}';

    protected $description = 'Import chord sheets from URLs, optionally crawling a listing page';

    public function __construct(
        private SongImporter $importer,
        private ChordParser $parser,
        private KeyDetector $keyDetector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $urls = $this->argument('urls');

        if ($list = $this->option('list')) {
            $found = $this->discoverLinks($list);
            $this->info(count($found).' song links found on '.$list);
            $urls = array_merge($urls, $found);
        }

        if ($urls === []) {
            $this->error('Nothing to import. Pass URLs or --list=<page>.');

            return self::FAILURE;
        }

        $results = [];
        foreach (array_values(array_unique($urls)) as $i => $url) {
            if ($i > 0) {
                sleep(2); // politeness between fetches
            }
            $results[] = $this->importOne($url);
        }

        $this->table(['URL', 'Status', 'Detail'], $results);

        $failed = count(array_filter($results, fn ($r) => $r[1] === 'failed'));

        return $failed === count($results) ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function importOne(string $url): array
    {
        try {
            $song = $this->importer->import($url);
        } catch (ImportException $e) {
            return [$url, 'failed', $e->getMessage()];
        }

        $song['content'] = $this->parser->normalize($song['content']);
        $song['title'] = $song['title'] ?: $url;

        // Importer keys can be things like "C major" — server regex is strict.
        if (! preg_match('/^[A-G][#b]?m?$/', (string) $song['key'])) {
            $song['key'] = null;
        }

        if ($this->option('dry-run')) {
            $lines = count(explode("\n", $song['content']));

            return [$url, 'dry-run', "{$song['title']} ({$lines} lines)"];
        }

        return $this->option('remote') ? $this->pushRemote($url, $song) : $this->saveLocal($url, $song);
    }

    private function saveLocal(string $url, array $song): array
    {
        $existing = Song::where('source_url', $url)->first();
        if ($existing) {
            return [$url, 'skipped', 'already imported: '.$existing->title];
        }

        $created = Song::create([
            'title' => $song['title'],
            'artist' => $song['artist'],
            'content' => $song['content'],
            'original_key' => $song['key'] ?? $this->keyDetector->detectFromContent($song['content']),
            'capo' => $song['capo'],
            'source_url' => $url,
        ]);

        return [$url, 'created', "#{$created->id} {$created->title}"];
    }

    private function pushRemote(string $url, array $song): array
    {
        $remote = config('services.chords_remote');
        if (! $remote['url'] || ! $remote['token']) {
            return [$url, 'failed', 'CHORDS_REMOTE_URL / CHORDS_REMOTE_TOKEN not set in .env'];
        }

        // Token in a custom header — Authorization is taken by nginx basic auth.
        $request = Http::withHeaders(['X-Import-Token' => $remote['token']])
            ->timeout(15)->acceptJson();

        if ($remote['basic'] && str_contains($remote['basic'], ':')) {
            [$user, $pass] = explode(':', $remote['basic'], 2);
            $request = $request->withBasicAuth($user, $pass);
        }

        $response = $request->post(rtrim($remote['url'], '/').'/api/import', [
            'title' => $song['title'],
            'artist' => $song['artist'],
            'content' => $song['content'],
            'original_key' => $song['key'],
            'capo' => $song['capo'],
            'source_url' => $url,
        ]);

        if (! $response->successful()) {
            return [$url, 'failed', "HTTP {$response->status()}: ".mb_substr($response->body(), 0, 120)];
        }

        return [$url, $response->json('status', '?'), "#{$response->json('id')} ".$response->json('title')];
    }

    /** @return list<string> */
    private function discoverLinks(string $listUrl): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
            'Accept' => 'text/html',
            'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
        ])->timeout(10)->get($listUrl);

        if (! $response->successful()) {
            $this->error("Listing page returned HTTP {$response->status()}.");

            return [];
        }

        $host = parse_url($listUrl, PHP_URL_HOST);
        preg_match_all('/href="(https?:\/\/[^"]+)"/i', $response->body(), $m);

        $links = array_filter($m[1], function ($link) use ($host, $listUrl) {
            return parse_url($link, PHP_URL_HOST) === $host
                && $link !== $listUrl
                // blog-post permalink shape used by chordtela & blogspot chord sites
                && preg_match('/\/\d{4}\/\d{2}\/[^\/]+\.html$/', $link);
        });

        return array_values(array_unique($links));
    }
}
