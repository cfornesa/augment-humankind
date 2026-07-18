// Shared client-side search/sort helpers for the admin media library page and
// the media picker modal. Loaded as a classic script (no build step); exposes
// a single window.AHMediaFilter global consumed by media.php's inline script
// and tiptap-editor.js.
(function () {
    'use strict';

    const text = value => String(value == null ? '' : value).toLowerCase();

    // Native files carry created_at, legacy media_assets carry uploaded_at;
    // library-page card items pass a pre-coalesced `date`.
    const itemDate = item => String(item.date || item.created_at || item.uploaded_at || '');

    // `_idx` is the item's position in the server-provided list (newest first);
    // it breaks ties so same-day items keep a deterministic order.
    const tiebreak = (a, b) => (a._idx || 0) - (b._idx || 0);

    const titleKey = item => text(item.title) || text(item.original_name || item.originalName) || String(item.id == null ? '' : item.id);

    const COMPARATORS = {
        newest: (a, b) => itemDate(b).localeCompare(itemDate(a)) || tiebreak(a, b),
        oldest: (a, b) => itemDate(a).localeCompare(itemDate(b)) || -tiebreak(a, b),
        title: (a, b) => titleKey(a).localeCompare(titleKey(b)) || tiebreak(a, b),
        type: (a, b) => text(a.mime_type || a.mime).localeCompare(text(b.mime_type || b.mime)) || tiebreak(a, b),
        size: (a, b) => (Number(b.byte_size || b.size || 0) - Number(a.byte_size || a.size || 0)) || tiebreak(a, b),
    };

    window.AHMediaFilter = {
        matches(item, query) {
            const q = String(query || '').trim().toLowerCase();
            if (q === '') return true;
            return [item.title, item.original_name || item.originalName, item.alt_text || item.altText, item.id]
                .some(field => text(field).includes(q));
        },
        comparator(sortKey) {
            return COMPARATORS[sortKey] || COMPARATORS.newest;
        },
        sortKeys() {
            return Object.keys(COMPARATORS);
        },
        debounce(fn, ms = 200) {
            let timer = null;
            return function (...args) {
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => { timer = null; fn.apply(this, args); }, ms);
            };
        },
    };
})();
