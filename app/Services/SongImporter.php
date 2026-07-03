<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class SongImporter
{
    private const PASTE_HINT = 'Copy the chords from the page and paste them below instead — or use the bookmarklet (see the tip under the URL field).';

    /**
     * Best-effort URL import. Ultimate Guitar pages carry structured JSON;
     * anything else falls back to grabbing the largest <pre> block, which is
     * how chordtela.com and most Indonesian chord blogs mark up their sheets.
     * Sites behind Cloudflare bot checks will 403 a server-side fetch —
     * every failure degrades to a "paste instead" message.
     *
     * @return array{title: ?string, artist: ?string, key: ?string, capo: ?int, content: string, source_url: string}
     *
     * @throws ImportException
     */
    public function import(string $url): array
    {
        try {
            $html = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15',
                'Accept' => 'text/html',
                'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
            ])->timeout(8)->get($url)->throw()->body();
        } catch (Throwable) {
            throw new ImportException('The site blocked the request or is unreachable. '.self::PASTE_HINT);
        }

        $result = str_contains(parse_url($url, PHP_URL_HOST) ?? '', 'ultimate-guitar')
            ? $this->fromUltimateGuitar($html)
            : $this->fromGenericPage($html);

        $result['source_url'] = $url;

        return $result;
    }

    private function fromUltimateGuitar(string $html): array
    {
        if (! preg_match('/class="js-store"\s+data-content="([^"]+)"/', $html, $m)) {
            throw new ImportException('Could not find chord data on the page. '.self::PASTE_HINT);
        }

        $data = json_decode(html_entity_decode($m[1], ENT_QUOTES), true);
        $view = $data['store']['page']['data'] ?? [];
        $content = $view['tab_view']['wiki_tab']['content'] ?? null;

        if (! is_string($content) || trim($content) === '') {
            throw new ImportException('Unexpected page format. '.self::PASTE_HINT);
        }

        $capo = $view['tab_view']['meta']['capo'] ?? null;

        return [
            'title' => $view['tab']['song_name'] ?? null,
            'artist' => $view['tab']['artist_name'] ?? null,
            'key' => $view['tab_view']['meta']['tonality'] ?? null,
            'capo' => is_numeric($capo) ? (int) $capo : null,
            'content' => str_replace(['[ch]', '[/ch]', '[tab]', '[/tab]'], '', $content),
        ];
    }

    /**
     * Two known markups: old Blogspot-era pages keep the sheet in a <pre>;
     * newer chordtela.com (WordPress) uses <div class="telabox"> with each
     * chord wrapped in an <a class="tbi-tooltip"> link.
     */
    private function fromGenericPage(string $html): array
    {
        $content = $this->largestBlock($html, '/<pre\b[^>]*>(.*?)<\/pre>/is')
            ?? $this->largestBlock($html, '/<div class="telabox">(.*?)<\/div>/is', literalWhitespaceSignificant: false);

        if ($content === null) {
            throw new ImportException('No chord block found on the page. '.self::PASTE_HINT);
        }

        return [
            'title' => $this->pageTitle($html),
            'artist' => null,
            'key' => null,
            'capo' => preg_match('/\bcapo\s*(?:fret|di\s*fret)?\s*(\d{1,2})/i', $content, $m)
                ? (int) $m[1]
                : null,
            'content' => $content,
        ];
    }

    /**
     * Inside <pre>, literal newlines are the line breaks. In normal HTML
     * (telabox) only <br> breaks lines — literal newlines/tabs are markup
     * formatting and must be dropped, alignment is done with &nbsp; runs.
     */
    private function largestBlock(string $html, string $pattern, bool $literalWhitespaceSignificant = true): ?string
    {
        preg_match_all($pattern, $html, $matches);

        $blocks = array_map(
            function ($block) use ($literalWhitespaceSignificant) {
                if (! $literalWhitespaceSignificant) {
                    $block = str_replace(["\r", "\n", "\t"], '', $block);
                }
                $block = preg_replace('/<br\s*\/?>/i', "\n", $block);

                return html_entity_decode(strip_tags($block), ENT_QUOTES | ENT_HTML5);
            },
            $matches[1]
        );

        usort($blocks, fn ($a, $b) => strlen($b) <=> strlen($a));

        return trim($blocks[0] ?? '') === '' ? null : $blocks[0];
    }

    private function pageTitle(string $html): ?string
    {
        $title = null;
        if (preg_match('/<meta\s+property="og:title"\s+content="([^"]+)"/i', $html, $m)) {
            $title = $m[1];
        } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $title = $m[1];
        }

        return $title === null ? null : self::cleanTitle($title);
    }

    /**
     * "Chord GOT7 - Encore | Chordtela" → "GOT7 - Encore",
     * "GOT7 - ENCORE Chord Dasar ©ChordTela.com" → "GOT7 - ENCORE".
     */
    public static function cleanTitle(string $title): string
    {
        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5);
        $title = preg_replace('/\s*[|–—©-]\s*(chordtela|chordindonesia|kunci\s*gitar|ultimate\s*guitar).*$/iu', '', $title);
        $title = preg_replace('/\s*©.*$/u', '', $title);
        $title = preg_replace('/^\s*(chord|kunci|kord)s?\s*(gitar|dasar)?\s*:?\s*/iu', '', $title);
        $title = preg_replace('/\s*(chord|kunci|kord)s?\s*(gitar|dasar)\s*$/iu', '', $title);

        return trim($title);
    }
}
