import { Head } from '@inertiajs/react';

/**
 * Đồng bộ <title> phía client khi điều hướng SPA giữa các trang marketing.
 * Các thẻ meta/OG/canonical đã được render server-side trong app.blade.php
 * (theo Seo singleton) nên crawler vẫn thấy đầy đủ khi tải trực tiếp từng URL.
 */
export function Seo({ seo, title }) {
    const resolved = title ?? seo?.title;

    return <Head>{resolved ? <title>{resolved}</title> : null}</Head>;
}
