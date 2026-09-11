import { usePage } from '@inertiajs/react';

/**
 * App display name for user-facing copy (replaces hardcoded Pushsale).
 * Prefers Inertia shared brand, then falls back to short SaleOps label.
 */
export function useAppName() {
    const page = usePage();
    const brand = page.props?.brand || {};
    return String(brand.name || brand.short || brand.admin_name || 'SaleOps').trim() || 'SaleOps';
}
