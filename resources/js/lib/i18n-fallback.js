/** Use client translation when key exists; otherwise fall back to server label. */
export function tOr(t, key, fallback) {
    const value = t(key);

    return value === key ? fallback : value;
}
