/**
 * Mở file nhãn vận đơn (PDF) trong tab mới.
 * Nếu hãng vận chuyển trả lỗi (JSON) thì ném Error với thông điệp dễ hiểu
 * thay vì mở tab hiển thị JSON thô.
 */
export async function openShippingLabel(url) {
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
    throw new Error(data?.message ?? 'Không in được nhãn vận đơn.');
}
