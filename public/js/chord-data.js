// Chord data: piano interval formulas, guitar open shapes, movable barre
// shapes, and the quality normalizer that maps parsed suffixes onto them.

const INTERVALS = {
    maj: [0, 4, 7],
    m: [0, 3, 7],
    5: [0, 7],
    7: [0, 4, 7, 10],
    maj7: [0, 4, 7, 11],
    m7: [0, 3, 7, 10],
    dim: [0, 3, 6],
    dim7: [0, 3, 6, 9],
    m7b5: [0, 3, 6, 10],
    aug: [0, 4, 8],
    sus2: [0, 2, 7],
    sus4: [0, 5, 7],
    '7sus4': [0, 5, 7, 10],
    6: [0, 4, 7, 9],
    m6: [0, 3, 7, 9],
    add9: [0, 4, 7, 14],
    9: [0, 4, 7, 10, 14],
    m9: [0, 3, 7, 10, 14],
    maj9: [0, 4, 7, 11, 14],
    11: [0, 4, 7, 10, 14, 17],
    13: [0, 4, 7, 10, 14, 21], // omits the 11th, standard voicing
};

// Map a raw quality suffix onto an INTERVALS key.
function normalizeQuality(quality) {
    let q = (quality || '')
        .replace(/^min/, 'm')
        .replace(/^-$/, 'm')
        .replace(/^M(?=\d|$)/, 'maj')
        .replace(/^\+$/, 'aug')
        .replace(/^°/, 'dim')
        .replace(/^o(?=7|$)/, 'dim')
        .replace(/^ø7?$/, 'm7b5')
        .replace(/^sus$/, 'sus4');
    if (q === '' || q === 'major') return 'maj';
    if (INTERVALS[q]) return q;
    // Degrade exotic suffixes: strip trailing alterations and retry (7b9 -> 7).
    const stripped = q.replace(/(?:b|#|add|no|omit|sus|\+|-)\d{1,2}$/, '');
    if (stripped !== q && INTERVALS[stripped]) return stripped;
    if (/^m/.test(q) && !/^maj/.test(q)) return 'm';
    return 'maj';
}

// Open/common guitar shapes: frets low-E -> high-e, -1 = mute.
// baseFret 1 unless noted; barre = fret spanned by a full barre.
const OPEN_SHAPES = {
    C: { frets: [-1, 3, 2, 0, 1, 0] },
    D: { frets: [-1, -1, 0, 2, 3, 2] },
    E: { frets: [0, 2, 2, 1, 0, 0] },
    F: { frets: [1, 3, 3, 2, 1, 1], barre: 1 },
    G: { frets: [3, 2, 0, 0, 0, 3] },
    A: { frets: [-1, 0, 2, 2, 2, 0] },
    B: { frets: [-1, 2, 4, 4, 4, 2], barre: 2, baseFret: 2 },
    Am: { frets: [-1, 0, 2, 2, 1, 0] },
    Bm: { frets: [-1, 2, 4, 4, 3, 2], barre: 2, baseFret: 2 },
    Dm: { frets: [-1, -1, 0, 2, 3, 1] },
    Em: { frets: [0, 2, 2, 0, 0, 0] },
    C7: { frets: [-1, 3, 2, 3, 1, 0] },
    D7: { frets: [-1, -1, 0, 2, 1, 2] },
    E7: { frets: [0, 2, 0, 1, 0, 0] },
    G7: { frets: [3, 2, 0, 0, 0, 1] },
    A7: { frets: [-1, 0, 2, 0, 2, 0] },
    B7: { frets: [-1, 2, 1, 2, 0, 2] },
    Am7: { frets: [-1, 0, 2, 0, 1, 0] },
    Dm7: { frets: [-1, -1, 0, 2, 1, 1] },
    Em7: { frets: [0, 2, 0, 0, 0, 0] },
    Cmaj7: { frets: [-1, 3, 2, 0, 0, 0] },
    Dmaj7: { frets: [-1, -1, 0, 2, 2, 2] },
    Fmaj7: { frets: [-1, -1, 3, 2, 1, 0] },
    Gmaj7: { frets: [3, 2, 0, 0, 0, 2] },
    Amaj7: { frets: [-1, 0, 2, 1, 2, 0] },
    Dsus2: { frets: [-1, -1, 0, 2, 3, 0] },
    Dsus4: { frets: [-1, -1, 0, 2, 3, 3] },
    Esus4: { frets: [0, 2, 2, 2, 0, 0] },
    Asus2: { frets: [-1, 0, 2, 2, 0, 0] },
    Asus4: { frets: [-1, 0, 2, 2, 3, 0] },
    Cadd9: { frets: [-1, 3, 2, 0, 3, 0] },
    Gadd9: { frets: [3, 2, 0, 2, 0, 3] },
};

// Movable shapes relative to the barre fret (0 = barre finger).
const MOVABLE_SHAPES = {
    E: {
        maj: [0, 2, 2, 1, 0, 0],
        m: [0, 2, 2, 0, 0, 0],
        7: [0, 2, 0, 1, 0, 0],
        m7: [0, 2, 0, 0, 0, 0],
        maj7: [0, 2, 1, 1, 0, 0],
        sus4: [0, 2, 2, 2, 0, 0],
        '7sus4': [0, 2, 0, 2, 0, 0],
    },
    A: {
        maj: [-1, 0, 2, 2, 2, 0],
        m: [-1, 0, 2, 2, 1, 0],
        7: [-1, 0, 2, 0, 2, 0],
        m7: [-1, 0, 2, 0, 1, 0],
        maj7: [-1, 0, 2, 1, 2, 0],
        sus4: [-1, 0, 2, 2, 3, 0],
        '7sus4': [-1, 0, 2, 0, 3, 0],
    },
};

// Qualities the movable shapes can't express degrade to the nearest base.
// Input is already a normalized INTERVALS key.
function movableQuality(q) {
    if (MOVABLE_SHAPES.E[q]) return q;
    const degrade = {
        dim: 'm', dim7: 'm7', m7b5: 'm7', m6: 'm', m9: 'm7',
        maj9: 'maj7', sus2: 'sus4', aug: 'maj', 5: 'maj', 6: 'maj',
        add9: 'maj', 9: '7', 11: '7', 13: '7',
    };
    return degrade[q] || 'maj';
}

// Guitar shape for any chord: dictionary first, then derive a barre chord
// from the movable E-shape (root on string 6) or A-shape (root on string 5).
function guitarShape(root, quality) {
    const normalized = normalizeQuality(quality);
    const dictName = root + (normalized === 'maj' ? '' : normalized);
    if (OPEN_SHAPES[dictName]) {
        const s = OPEN_SHAPES[dictName];
        return { frets: s.frets, barre: s.barre || null, baseFret: s.baseFret || 1 };
    }

    const rootPc = PITCH_CLASS[root];
    if (rootPc === undefined) return null;

    const q = movableQuality(normalized);
    const eFret = ((rootPc - PITCH_CLASS.E) % 12 + 12) % 12 || 12;
    const aFret = ((rootPc - PITCH_CLASS.A) % 12 + 12) % 12 || 12;
    const useE = eFret <= aFret;
    const rel = (useE ? MOVABLE_SHAPES.E : MOVABLE_SHAPES.A)[q];
    const base = useE ? eFret : aFret;

    return {
        frets: rel.map((f) => (f < 0 ? -1 : f + base)),
        barre: base,
        baseFret: base,
    };
}

// Piano notes as semitone offsets from C (may exceed 12 for extensions).
function pianoNotes(root, quality) {
    const rootPc = PITCH_CLASS[root];
    if (rootPc === undefined) return null;
    return INTERVALS[normalizeQuality(quality)].map((i) => rootPc + i);
}
