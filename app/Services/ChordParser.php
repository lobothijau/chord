<?php

namespace App\Services;

class ChordParser
{
    /**
     * Case-sensitive on purpose: lowercase words ("go", "am") must never match.
     * Bare "o" for dim is deliberately excluded so "Go"/"Do" stay lyrics.
     */
    public const CHORD_REGEX = '/^
        (?<root>[A-G](?:\#|b)?)
        (?<quality>
            (?:maj|min|dim|aug|sus|add|m|M|\+|°)?
            (?:\d{1,2})?
            (?:(?:maj|Maj|sus|add|b|\#|\+|\-|no|omit)\d{1,2})*
        )
        (?:\/(?<bass>[A-G](?:\#|b)?))?
    $/xu';

    private const NOISE = ['|', '||', '-', '–', '.', ',', 'N.C.', 'NC', '/', '%'];

    public function isChord(string $token): bool
    {
        return (bool) preg_match(self::CHORD_REGEX, $token);
    }

    /**
     * Normalize pasted text into the canonical stored form.
     */
    public function normalize(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $content = str_replace("\u{00A0}", ' ', $content);
        $content = str_replace("\t", '    ', $content);

        $lines = array_map(fn ($line) => rtrim($line), explode("\n", $content));

        return trim(implode("\n", $lines), "\n");
    }

    public function classifyLine(string $line): LineType
    {
        if (trim($line) === '') {
            return LineType::Blank;
        }

        if (preg_match('/^\s*[eEBGDAd]\|[-\dhpbrxs\/\\\\~|().\s]*$/', $line)) {
            return LineType::Tab;
        }

        if (preg_match('/^\s*\[([^\]]{1,30})\]\s*$/', $line, $m) && ! $this->isChord(trim($m[1]))) {
            return LineType::Section;
        }

        return $this->isChordLine($line) ? LineType::Chord : LineType::Lyric;
    }

    public function isChordLine(string $line): bool
    {
        $tokens = preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY);
        if ($tokens === []) {
            return false;
        }

        $meaningful = 0;
        $chords = 0;

        foreach ($tokens as $token) {
            $bare = trim($token, '()[]');
            if ($bare === ''
                || in_array($bare, self::NOISE, true)
                || preg_match('/^[x×]\d+$/iu', $bare)) {
                continue;
            }
            $meaningful++;
            if ($this->isChord($bare)) {
                $chords++;
            }
        }

        return $meaningful > 0 && ($chords / $meaningful) >= 0.75;
    }

    /**
     * All chord tokens in the sheet, in order of appearance.
     *
     * @return list<string>
     */
    public function extractChords(string $content): array
    {
        $chords = [];

        foreach (explode("\n", $content) as $line) {
            if ($this->classifyLine($line) !== LineType::Chord) {
                continue;
            }
            foreach (preg_split('/\s+/', trim($line), -1, PREG_SPLIT_NO_EMPTY) as $token) {
                $bare = trim($token, '()[]');
                if ($bare !== '' && $this->isChord($bare)) {
                    $chords[] = $bare;
                }
            }
        }

        return $chords;
    }
}
