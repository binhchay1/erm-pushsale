/**
 * Open waybill label PDF in a new tab.
 * If carrier returns JSON error, throw with a readable message instead of opening raw JSON.
 */
export async function openShippingLabel(url, fallbackMessage = 'Could not print waybill label.') {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/pdf,application/json' },
    });

    const contentType = response.headers.get('content-type') ?? '';

    if (response.ok && !contentType.includes('json')) {
        const blob = await response.blob();
        const blobUrl = URL.createObjectURL(blob);
        window.open(blobUrl, '_blank', 'noopener,noreferrer');
        setTimeout(() => URL.revokeObjectURL(blobUrl), 60_000);
        return;
    }

    const data = await response.json().catch(() => null);
    throw new Error(data?.message ?? fallbackMessage);
}
