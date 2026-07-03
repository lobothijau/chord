<?php

namespace App\Services;

enum LineType
{
    case Chord;
    case Lyric;
    case Section;
    case Tab;
    case Blank;
}
