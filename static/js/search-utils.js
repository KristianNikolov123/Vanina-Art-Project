/**
 * Multi-word row search: "Вик 34" matches if every token appears somewhere in the row haystack.
 */
function parseSearchTokens(raw) {
    return (raw || '').trim().toLowerCase().split(/\s+/).filter(Boolean);
}

function rowMatchesTokenSearch(haystack, rawQuery) {
    const tokens = parseSearchTokens(rawQuery);
    if (tokens.length === 0) {
        return true;
    }
    const text = (haystack || '').toLowerCase();
    return tokens.every((token) => text.includes(token));
}

function flattenOrderSearchValue(value) {
    if (value == null || value === '') {
        return '';
    }
    if (typeof value === 'boolean') {
        return value ? '1' : '';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }
    return String(value);
}

function buildOrderRowSearchHaystack(row) {
    let parts = [(row.textContent || '').replace(/\s+/g, ' ').trim()];

    if (row.dataset.order) {
        try {
            const data = JSON.parse(row.dataset.order);
            Object.values(data).forEach((value) => {
                const flat = flattenOrderSearchValue(value);
                if (flat !== '') {
                    parts.push(flat);
                }
            });
        } catch (e) {
            /* ignore bad JSON */
        }
    }

    return parts.join(' ');
}
