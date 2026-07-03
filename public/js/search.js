// Debounced live search on the song list.

(function initSearch() {
    const input = document.getElementById('search-input');
    const container = document.getElementById('song-list-container');
    if (!input || !container) return;

    let debounceTimer = null;
    let controller = null;

    async function refresh() {
        const q = input.value.trim();
        const url = q === '' ? '/songs' : `/songs?q=${encodeURIComponent(q)}`;

        controller?.abort(); // drop stale in-flight response
        controller = new AbortController();

        try {
            const res = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!res.ok) return;
            container.innerHTML = await res.text();
            history.replaceState(null, '', url);
        } catch {
            /* aborted or offline — keep current list */
        }
    }

    input.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(refresh, 300);
    });

    // Enter still works via normal form submit; nothing else to do here.
})();
