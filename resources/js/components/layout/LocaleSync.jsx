import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

import { useI18n } from '@/providers/I18nProvider';

/** Keeps client i18n dictionary aligned with server `locale` shared prop after Inertia visits. */
export function LocaleSync() {
    const { locale: pageLocale } = usePage().props;
    const { syncLocaleFromServer } = useI18n();

    useEffect(() => {
        if (pageLocale) {
            syncLocaleFromServer(pageLocale);
        }
    }, [pageLocale, syncLocaleFromServer]);

    return null;
}
