// SVG chord diagrams (guitar fretboard + piano keys) in one shared popover.

const SVG_NS = 'http://www.w3.org/2000/svg';

function svgEl(tag, attrs) {
    const el = document.createElementNS(SVG_NS, tag);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
    return el;
}

function cssVar(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}

// --- guitar fretboard: 6 strings, 5 fret rows ---
function buildGuitarSvg(shape) {
    const strings = 6;
    const fretRows = 5;
    const strGap = 13;
    const fretGap = 16;
    const left = 16;
    const top = 22;
    const width = left * 2 + strGap * (strings - 1);
    const height = top + fretGap * fretRows + 14;

    const fg = cssVar('--text', '#000');
    const accent = cssVar('--chord', '#1d4ed8');
    const svg = svgEl('svg', { width, height, viewBox: `0 0 ${width} ${height}` });

    // frets (horizontal) + strings (vertical)
    for (let f = 0; f <= fretRows; f++) {
        svg.appendChild(svgEl('line', {
            x1: left, y1: top + f * fretGap,
            x2: left + strGap * (strings - 1), y2: top + f * fretGap,
            stroke: fg, 'stroke-width': f === 0 && shape.baseFret <= 1 ? 3 : 1,
        }));
    }
    for (let s = 0; s < strings; s++) {
        svg.appendChild(svgEl('line', {
            x1: left + s * strGap, y1: top,
            x2: left + s * strGap, y2: top + fretRows * fretGap,
            stroke: fg, 'stroke-width': 1,
        }));
    }

    // position label when the diagram doesn't start at the nut
    if (shape.baseFret > 1) {
        const label = svgEl('text', {
            x: left - 4, y: top + fretGap * 0.7,
            'text-anchor': 'end', 'font-size': 9, fill: fg,
        });
        label.textContent = `${shape.baseFret}fr`;
        svg.appendChild(label);
    }

    // barre bar
    if (shape.barre) {
        const row = shape.barre - shape.baseFret; // 0-indexed row of the barre
        const played = shape.frets.map((f, i) => (f >= 0 ? i : -1)).filter((i) => i >= 0);
        const first = Math.min(...played);
        const last = Math.max(...played);
        svg.appendChild(svgEl('rect', {
            x: left + first * strGap - 4,
            y: top + row * fretGap + fretGap / 2 - 4,
            width: (last - first) * strGap + 8,
            height: 8, rx: 4, fill: accent,
        }));
    }

    // dots + open/mute markers
    shape.frets.forEach((fret, s) => {
        const x = left + s * strGap;
        if (fret < 0 || fret === 0) {
            const marker = svgEl('text', {
                x, y: top - 7, 'text-anchor': 'middle', 'font-size': 9, fill: fg,
            });
            marker.textContent = fret < 0 ? '✕' : '○';
            svg.appendChild(marker);
            return;
        }
        if (fret === shape.barre) return; // covered by the barre bar
        const row = fret - shape.baseFret;
        svg.appendChild(svgEl('circle', {
            cx: x, cy: top + row * fretGap + fretGap / 2, r: 4.5, fill: accent,
        }));
    });

    return svg;
}

// --- piano keyboard with pressed keys highlighted ---
const WHITE_PCS = [0, 2, 4, 5, 7, 9, 11];
// black key pitch class -> index of the white key it sits after (within octave)
const BLACK_AFTER_WHITE = { 1: 0, 3: 1, 6: 3, 8: 4, 10: 5 };

function buildPianoSvg(notes) {
    const octaves = Math.max(2, Math.ceil((Math.max(...notes) + 1) / 12));
    const whiteW = 20;
    const whiteH = 84;
    const blackW = 12;
    const blackH = 52;
    const width = whiteW * 7 * octaves + 2;
    const height = whiteH + 2;

    const fg = cssVar('--text', '#000');
    const accent = cssVar('--chord', '#1d4ed8');
    const pressed = new Set(notes);
    const svg = svgEl('svg', { width, height, viewBox: `0 0 ${width} ${height}` });

    // white keys
    for (let o = 0; o < octaves; o++) {
        WHITE_PCS.forEach((pc, i) => {
            const note = o * 12 + pc;
            svg.appendChild(svgEl('rect', {
                x: 1 + (o * 7 + i) * whiteW, y: 1,
                width: whiteW, height: whiteH,
                fill: pressed.has(note) ? accent : 'white',
                stroke: fg, 'stroke-width': 1,
            }));
        });
    }
    // black keys on top
    for (let o = 0; o < octaves; o++) {
        for (const [pcStr, afterWhite] of Object.entries(BLACK_AFTER_WHITE)) {
            const note = o * 12 + Number(pcStr);
            svg.appendChild(svgEl('rect', {
                x: 1 + (o * 7 + afterWhite) * whiteW + whiteW - blackW / 2, y: 1,
                width: blackW, height: blackH,
                fill: pressed.has(note) ? accent : '#111',
                stroke: fg, 'stroke-width': 1,
            }));
        }
    }

    return svg;
}

// --- shared popover ---
(function initChordPopover() {
    const popover = document.getElementById('chord-popover');
    const sheet = document.getElementById('sheet');
    if (!popover || !sheet) return;

    let hoverTimer = null;
    let currentEl = null;

    function show(chordEl) {
        const name = chordEl.textContent.trim();
        const parts = splitChord(name);
        if (!parts) return;

        popover.innerHTML = '';
        const title = document.createElement('h3');
        title.textContent = name;
        popover.appendChild(title);

        const wrap = document.createElement('div');
        wrap.className = 'diagrams';
        const shape = guitarShape(parts.root, parts.quality);
        if (shape) wrap.appendChild(buildGuitarSvg(shape));
        const notes = pianoNotes(parts.root, parts.quality);
        if (notes) wrap.appendChild(buildPianoSvg(notes));
        popover.appendChild(wrap);

        popover.hidden = false;
        currentEl = chordEl;

        const target = chordEl.getBoundingClientRect();
        const pop = popover.getBoundingClientRect();
        let left = target.left + target.width / 2 - pop.width / 2;
        left = Math.max(8, Math.min(left, window.innerWidth - pop.width - 8));
        let topPos = target.top - pop.height - 8;
        if (topPos < 8) topPos = target.bottom + 8;
        popover.style.left = `${left}px`;
        popover.style.top = `${topPos}px`;
    }

    function hide() {
        popover.hidden = true;
        currentEl = null;
    }

    sheet.addEventListener('pointerover', (e) => {
        if (e.pointerType !== 'mouse') return;
        const chordEl = e.target.closest('.chord');
        if (!chordEl) return;
        clearTimeout(hoverTimer);
        hoverTimer = setTimeout(() => show(chordEl), 120);
    });

    sheet.addEventListener('pointerout', (e) => {
        if (e.pointerType !== 'mouse') return;
        if (e.target.closest('.chord')) {
            clearTimeout(hoverTimer);
            hide();
        }
    });

    // tap/click toggles (touch has no hover)
    document.addEventListener('click', (e) => {
        const chordEl = e.target.closest('.chord');
        if (chordEl) {
            e.preventDefault();
            if (currentEl === chordEl && !popover.hidden) hide();
            else show(chordEl);
        } else if (!popover.contains(e.target)) {
            hide();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') hide();
    });

    window.addEventListener('scroll', hide, { passive: true });
})();
