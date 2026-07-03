// Song form: live preview (server-rendered) + best-effort URL import.

(function initSongForm() {
    const content = document.getElementById('content');
    const previewPane = document.getElementById('preview-pane');
    if (!content || !previewPane) return;

    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let debounceTimer = null;

    async function refreshPreview() {
        const text = content.value;
        if (text.trim() === '') {
            previewPane.innerHTML = '<span class="muted">Paste a sheet to preview&hellip;</span>';
            return;
        }
        try {
            const res = await fetch('/songs/preview', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ content: text }),
            });
            if (res.ok) previewPane.innerHTML = await res.text();
        } catch {
            /* preview is best-effort */
        }
    }

    content.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refreshPreview, 400);
    });

    if (content.value.trim() !== '') refreshPreview();

    // Bookmarklet hands the sheet over via postMessage (any origin — payload
    // only prefills the form, user reviews before saving).
    window.addEventListener('message', (e) => {
        const d = e.data;
        if (!d || d.chords !== 1 || typeof d.content !== 'string') return;
        content.value = d.content;
        e.source?.postMessage('chords:ack', '*');
        refreshPreview();
    });

    // --- URL import ---
    const urlInput = document.getElementById('fetch-url-input');
    const fetchBtn = document.getElementById('fetch-url-btn');
    const fetchError = document.getElementById('fetch-error');

    fetchBtn.addEventListener('click', async () => {
        const url = urlInput.value.trim();
        if (url === '') return;

        fetchBtn.disabled = true;
        fetchBtn.textContent = 'Fetching…';
        fetchError.hidden = true;

        try {
            const res = await fetch('/songs/fetch-url', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ url }),
            });
            const data = await res.json();

            if (!res.ok) {
                throw new Error(data.message || 'Import failed. Paste the chord text instead.');
            }

            if (data.title) document.getElementById('title').value = data.title;
            if (data.artist) document.getElementById('artist').value = data.artist;
            if (data.key) document.getElementById('original_key').value = data.key;
            if (data.capo) document.getElementById('capo').value = data.capo;
            content.value = data.content;
            document.getElementById('source_url').value = data.source_url || url;
            refreshPreview();
        } catch (err) {
            fetchError.textContent = err.message;
            fetchError.hidden = false;
        } finally {
            fetchBtn.disabled = false;
            fetchBtn.textContent = 'Fetch';
        }
    });
})();
