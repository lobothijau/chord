<?php

namespace Tests\Unit;

use App\Services\ChordParser;
use App\Services\LineType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChordParserTest extends TestCase
{
    private ChordParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ChordParser;
    }

    public static function chordTokens(): array
    {
        return [
            ['C'], ['F#m'], ['Bb'], ['Asus4'], ['Gmaj7'], ['Ddim'], ['Eaug'],
            ['Cadd9'], ['F/G'], ['D/F#'], ['A7sus4'], ['C#m7b5'], ['B7'],
            ['E13'], ['Am'], ['Cm7'], ['G/B'], ['Dsus2'], ['Ebmaj7'], ['A+'],
        ];
    }

    public static function nonChordTokens(): array
    {
        return [
            ['I'], ['Hello'], ['a'], ['Go'], ['Do'], ['Amen'], ['H'],
            ['Ammo'], ['Chord'], ['and'], ['Baby'], ['Feel'],
        ];
    }

    #[DataProvider('chordTokens')]
    public function test_recognizes_chord_tokens(string $token): void
    {
        $this->assertTrue($this->parser->isChord($token), "Expected '{$token}' to be a chord");
    }

    #[DataProvider('nonChordTokens')]
    public function test_rejects_non_chord_tokens(string $token): void
    {
        $this->assertFalse($this->parser->isChord($token), "Expected '{$token}' to NOT be a chord");
    }

    public function test_classifies_chord_line(): void
    {
        $this->assertSame(LineType::Chord, $this->parser->classifyLine('Am   F   C   G'));
        $this->assertSame(LineType::Chord, $this->parser->classifyLine('C  G/B  Am  (x2)'));
        $this->assertSame(LineType::Chord, $this->parser->classifyLine('| Em | D | C | C |'));
    }

    public function test_classifies_lyric_line(): void
    {
        $this->assertSame(LineType::Lyric, $this->parser->classifyLine('Am I ever gonna see your face again'));
        $this->assertSame(LineType::Lyric, $this->parser->classifyLine('Today is gonna be the day'));
    }

    public function test_classifies_section_header(): void
    {
        $this->assertSame(LineType::Section, $this->parser->classifyLine('[Chorus]'));
        $this->assertSame(LineType::Section, $this->parser->classifyLine('[Verse 1]'));
    }

    public function test_classifies_tab_line(): void
    {
        $this->assertSame(LineType::Tab, $this->parser->classifyLine('e|--0--2--3--|'));
        $this->assertSame(LineType::Tab, $this->parser->classifyLine('B|--1h3p1----|'));
    }

    public function test_classifies_blank_line(): void
    {
        $this->assertSame(LineType::Blank, $this->parser->classifyLine(''));
        $this->assertSame(LineType::Blank, $this->parser->classifyLine('   '));
    }

    public function test_normalize_cleans_pasted_text(): void
    {
        $raw = "Am\tF\r\nHello\u{00A0}world   \r\n";

        $this->assertSame("Am    F\nHello world", $this->parser->normalize($raw));
    }

    public function test_extract_chords_returns_ordered_tokens(): void
    {
        $content = "[Verse]\nAm   F   C\nSome lyric line here\nG    Em";

        $this->assertSame(['Am', 'F', 'C', 'G', 'Em'], $this->parser->extractChords($content));
    }
}
