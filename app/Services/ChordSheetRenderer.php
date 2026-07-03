<?php

namespace App\Services;

class ChordSheetRenderer
{
    public function __construct(private ChordParser $parser)
    {
    }

    public function render(string $content): string
    {
        $html = '';

        foreach (explode("\n", $content) as $line) {
            $html .= match ($this->parser->classifyLine($line)) {
                LineType::Blank => '<div class="line blank">&nbsp;</div>',
                LineType::Section => '<div class="line section">'.e(trim($line)).'</div>',
                LineType::Tab => '<div class="line tab">'.e($line).'</div>',
                LineType::Chord => '<div class="line chords">'.$this->renderChordLine($line).'</div>',
                LineType::Lyric => '<div class="line lyric">'.e($line).'</div>',
            };
        }

        return $html;
    }

    /**
     * Wrap chord tokens in spans; whitespace between tokens passes through
     * untouched so monospace column alignment is preserved.
     */
    private function renderChordLine(string $line): string
    {
        return preg_replace_callback('/\S+/u', function ($m) {
            $bare = trim($m[0], '()[]');
            if ($bare === '' || ! $this->parser->isChord($bare)) {
                return e($m[0]);
            }

            $span = '<span class="chord" data-chord="'.e($bare).'">'.e($bare).'</span>';

            return str_replace(e($bare), $span, e($m[0]));
        }, $line);
    }
}
