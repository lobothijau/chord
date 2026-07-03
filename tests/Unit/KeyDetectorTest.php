<?php

namespace Tests\Unit;

use App\Services\ChordParser;
use App\Services\KeyDetector;
use PHPUnit\Framework\TestCase;

class KeyDetectorTest extends TestCase
{
    private KeyDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new KeyDetector(new ChordParser);
    }

    public function test_detects_major_key_from_frequency_and_cadence(): void
    {
        $this->assertSame('G', $this->detector->detect(['G', 'C', 'D', 'G', 'C', 'G']));
    }

    public function test_detects_minor_key(): void
    {
        $this->assertSame('Em', $this->detector->detect(['Em', 'C', 'G', 'D', 'Em']));
    }

    public function test_strips_extensions_and_slash_bass(): void
    {
        $this->assertSame('Am', $this->detector->detect(['Am7', 'F/A', 'G', 'Am7']));
    }

    public function test_returns_null_for_empty_input(): void
    {
        $this->assertNull($this->detector->detect([]));
    }

    public function test_detects_from_content(): void
    {
        $content = "[Verse]\nC   F   G\nSome lyrics\nAm  F   C";

        $this->assertSame('C', $this->detector->detectFromContent($content));
    }
}
