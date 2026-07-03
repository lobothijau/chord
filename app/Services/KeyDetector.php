<?php

namespace App\Services;

class KeyDetector
{
    public function __construct(private ChordParser $parser)
    {
    }

    public function detectFromContent(string $content): ?string
    {
        return $this->detect($this->parser->extractChords($content));
    }

    /**
     * @param  list<string>  $chords
     */
    public function detect(array $chords): ?string
    {
        if ($chords === []) {
            return null;
        }

        $names = array_values(array_filter(array_map($this->toKeyCandidate(...), $chords)));
        if ($names === []) {
            return null;
        }

        $scores = array_count_values($names);
        $scores[$names[0]] += 2;
        $scores[$names[count($names) - 1]] += 3;
        arsort($scores);

        return array_key_first($scores);
    }

    /**
     * "Am7" → "Am", "Gsus4" → "G", "F/A" → "F", "Cmaj7" → "C".
     */
    private function toKeyCandidate(string $chord): ?string
    {
        if (! preg_match(ChordParser::CHORD_REGEX, $chord, $m)) {
            return null;
        }

        $quality = $m['quality'] ?? '';
        $isMinor = $quality !== ''
            && str_starts_with($quality, 'm')
            && ! str_starts_with($quality, 'maj');

        return $m['root'].($isMinor ? 'm' : '');
    }
}
