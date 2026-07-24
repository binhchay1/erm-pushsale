import viGuides from '@/i18n/guides/vi';
import enGuides from '@/i18n/guides/en';

const BY_LOCALE = { vi: viGuides, en: enGuides };

/** Find the guide with the longest pathname prefix match. */
export function findPageGuide(pathname, locale = 'vi') {
    const guides = BY_LOCALE[locale] ?? BY_LOCALE.vi;
    let best = null;

    for (const guide of guides) {
        if (pathname === guide.path || pathname.startsWith(guide.path + '/')) {
            if (!best || guide.path.length > best.path.length) {
                best = guide;
            }
        }
    }

    // Extra report child routes (e.g. /admin/sales/reports/work) match parent prefix
    return best;
}
