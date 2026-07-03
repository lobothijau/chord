// Auto-scroll: rAF loop with a fractional-pixel accumulator so sub-pixel
// speeds still move the page smoothly.

(function initAutoScroll() {
    const toggleBtn = document.getElementById('scroll-toggle');
    const speedInput = document.getElementById('scroll-speed');
    const player = document.getElementById('player');
    if (!toggleBtn || !speedInput || !player) return;

    const storageKey = `chords:${player.dataset.songId}:scrollSpeed`;

    let running = false;
    let pxPerSec = Number(localStorage.getItem(storageKey)) || 20;
    let last = null;
    let pos = null; // fractional scroll position; scrollTop accepts floats

    pxPerSec = Math.min(120, Math.max(5, pxPerSec));
    speedInput.value = pxPerSec;

    const scroller = document.scrollingElement || document.documentElement;

    function frame(ts) {
        if (!running) return;
        if (last !== null) {
            // scrollbar drag or programmatic jump: resync instead of fighting it
            if (pos === null || Math.abs(scroller.scrollTop - pos) > 4) {
                pos = scroller.scrollTop;
            }
            pos += (pxPerSec * (ts - last)) / 1000;
            scroller.scrollTop = pos;

            const bottom = window.innerHeight + scroller.scrollTop
                >= scroller.scrollHeight - 1;
            if (bottom) {
                stop();
                return;
            }
        }
        last = ts;
        requestAnimationFrame(frame);
    }

    function start() {
        running = true;
        last = null;
        pos = null;
        toggleBtn.innerHTML = '&#10074;&#10074;'; // pause icon
        requestAnimationFrame(frame);
    }

    function stop() {
        running = false;
        toggleBtn.innerHTML = '&#9654;'; // play icon
    }

    function setSpeed(value) {
        pxPerSec = Math.min(120, Math.max(5, value));
        speedInput.value = pxPerSec;
        localStorage.setItem(storageKey, pxPerSec);
    }

    toggleBtn.addEventListener('click', () => (running ? stop() : start()));
    speedInput.addEventListener('input', () => setSpeed(Number(speedInput.value)));

    document.addEventListener('keydown', (e) => {
        if (e.target.matches('input, textarea, select')) return;
        if (e.key === ' ') {
            e.preventDefault();
            running ? stop() : start();
        } else if (running && (e.key === 'ArrowUp' || e.key === '+')) {
            e.preventDefault();
            setSpeed(pxPerSec + 5);
        } else if (running && (e.key === 'ArrowDown' || e.key === '-')) {
            e.preventDefault();
            setSpeed(pxPerSec - 5);
        }
    });

    // manual scrolling wins over auto-scroll
    window.addEventListener('wheel', () => running && stop(), { passive: true });
    window.addEventListener('touchmove', () => running && stop(), { passive: true });
})();
