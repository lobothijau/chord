// Song player page: transpose toolbar + font size, persisted per song.

(function initPlayer() {
    const player = document.getElementById('player');
    if (!player) return;

    const songId = player.dataset.songId;
    const originalKey = player.dataset.key || '';
    const keyBadge = document.getElementById('key-badge');
    const sheet = document.getElementById('sheet');
    const chords = document.querySelectorAll('#sheet .chord');

    // --- transpose ---
    const transposeKey = `chords:${songId}:transpose`;
    let offset = Number(localStorage.getItem(transposeKey)) || 0;

    function apply() {
        const useFlats = shouldUseFlats(originalKey, offset, sheet.textContent);
        chords.forEach((el) => {
            el.textContent = offset === 0
                ? el.dataset.chord
                : transposeChord(el.dataset.chord, offset, useFlats);
        });

        if (keyBadge) {
            if (!originalKey) {
                keyBadge.textContent = offset === 0 ? '—' : `${offset > 0 ? '+' : ''}${offset}`;
            } else if (offset === 0) {
                keyBadge.textContent = originalKey;
            } else {
                const newKey = transposeChord(originalKey, offset, useFlats);
                keyBadge.textContent = `${originalKey} → ${newKey} (${offset > 0 ? '+' : ''}${offset})`;
            }
        }

        localStorage.setItem(transposeKey, offset);
    }

    function shift(steps) {
        offset = Math.max(-11, Math.min(11, offset + steps));
        apply();
    }

    document.getElementById('transpose-up').addEventListener('click', () => shift(1));
    document.getElementById('transpose-down').addEventListener('click', () => shift(-1));
    document.getElementById('transpose-reset').addEventListener('click', () => {
        offset = 0;
        apply();
    });

    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === ']') shift(1);
        if (e.key === '[') shift(-1);
    });

    if (offset !== 0) apply();

    // --- font size ---
    const fontKey = `chords:${songId}:fontSize`;
    let fontSize = Number(localStorage.getItem(fontKey)) || 0.95;

    function applyFont() {
        fontSize = Math.min(1.6, Math.max(0.6, fontSize));
        sheet.style.setProperty('--sheet-font', `${fontSize}rem`);
        sheet.style.fontSize = `${fontSize}rem`;
        localStorage.setItem(fontKey, fontSize);
    }

    document.getElementById('font-up').addEventListener('click', () => {
        fontSize += 0.05;
        applyFont();
    });
    document.getElementById('font-down').addEventListener('click', () => {
        fontSize -= 0.05;
        applyFont();
    });

    if (fontSize !== 0.95) applyFont();
})();
