// Note math + chord transposition. Plain script; exposes globals used by
// player.js and diagrams.js.

const SHARP_NOTES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B'];
const FLAT_NOTES = ['C', 'Db', 'D', 'Eb', 'E', 'F', 'Gb', 'G', 'Ab', 'A', 'Bb', 'B'];

const PITCH_CLASS = {
    C: 0, 'C#': 1, Db: 1, D: 2, 'D#': 3, Eb: 3, E: 4, F: 5,
    'F#': 6, Gb: 6, G: 7, 'G#': 8, Ab: 8, A: 9, 'A#': 10, Bb: 10, B: 11,
};

const FLAT_KEYS = new Set(['F', 'Bb', 'Eb', 'Ab', 'Db', 'Gb', 'Dm', 'Gm', 'Cm', 'Fm', 'Bbm', 'Ebm']);

const CHORD_PARTS_RE = /^([A-G][#b]?)([^/]*)(?:\/([A-G][#b]?))?$/;

function transposeNote(note, steps, useFlats) {
    const pc = PITCH_CLASS[note];
    if (pc === undefined) return note;
    const i = ((pc + steps) % 12 + 12) % 12;
    return (useFlats ? FLAT_NOTES : SHARP_NOTES)[i];
}

function transposeChord(chord, steps, useFlats) {
    const m = chord.match(CHORD_PARTS_RE);
    if (!m) return chord;
    let out = transposeNote(m[1], steps, useFlats) + m[2];
    if (m[3]) out += '/' + transposeNote(m[3], steps, useFlats);
    return out;
}

// "F#m" -> { root: "F#", quality: "m", bass: null }
function splitChord(chord) {
    const m = chord.match(CHORD_PARTS_RE);
    if (!m) return null;
    return { root: m[1], quality: m[2] || '', bass: m[3] || null };
}

// Spelling choice: transpose the song's key and prefer flats when the
// resulting key is a flat key. Falls back to counting accidentals in the sheet.
function shouldUseFlats(originalKey, steps, sheetText) {
    if (originalKey) {
        const m = originalKey.match(/^([A-G][#b]?)(m?)$/);
        if (m) {
            const newRoot = transposeNote(m[1], steps, false);
            if (FLAT_KEYS.has(newRoot + m[2])) return true;
            const newRootFlat = transposeNote(m[1], steps, true);
            return FLAT_KEYS.has(newRootFlat + m[2]);
        }
    }
    const flats = (sheetText.match(/[A-G]b/g) || []).length;
    const sharps = (sheetText.match(/[A-G]#/g) || []).length;
    return flats > sharps;
}
