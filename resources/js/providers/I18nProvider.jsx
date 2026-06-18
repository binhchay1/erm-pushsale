import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';

import en from '@/i18n/locales/en/index';
import vi from '@/i18n/locales/vi/index';
import { setTranslateLocale } from '@/i18n/translate';

const DICTS = { vi, en };

const I18nContext = createContext(null);

function getByPath(obj, path) {
    return path.split('.').reduce((acc, key) => acc?.[key], obj);
}

function interpolate(str, params = {}) {
    if (typeof str !== 'string') {
        return str;
    }

    return str.replace(/:(\w+)/g, (_, key) => (params[key] !== undefined ? String(params[key]) : `:${key}`));
}

export function I18nProvider({ children, initialLocale = 'vi', localeMeta = null }) {
    const [locale, setLocaleState] = useState(initialLocale);
    const pendingLocale = useRef(null);

    useEffect(() => {
        setTranslateLocale(locale);
    }, [locale]);

    const applyLocale = useCallback((next) => {
        if (!next || next === locale) {
            return;
        }

        setLocaleState(next);
        setTranslateLocale(next);
    }, [locale]);

    const setLocale = useCallback((next) => {
        if (!next || next === locale) {
            return;
        }

        pendingLocale.current = next;
        applyLocale(next);

        router.post(
            '/locale',
            { locale: next },
            {
                preserveScroll: true,
                onSuccess: () => {
                    router.reload({ preserveScroll: true });
                },
                onError: () => {
                    pendingLocale.current = null;
                    router.reload({ only: ['locale', 'preferences'], preserveScroll: true });
                },
            },
        );
    }, [applyLocale, locale]);

    const syncLocaleFromServer = useCallback((next) => {
        if (!next || !['vi', 'en'].includes(next)) {
            return;
        }

        if (pendingLocale.current && pendingLocale.current !== next) {
            return;
        }

        pendingLocale.current = null;
        setLocaleState(next);
        setTranslateLocale(next);
    }, []);

    const t = useCallback(
        (key, params) => {
            const dict = DICTS[locale] ?? DICTS.vi;
            const value = getByPath(dict, key) ?? getByPath(DICTS.vi, key) ?? key;

            return interpolate(value, params);
        },
        [locale],
    );

    const value = useMemo(
        () => ({
            locale,
            setLocale,
            syncLocaleFromServer,
            t,
            locales: localeMeta ?? { vi: { short: 'VI' }, en: { short: 'EN' } },
        }),
        [locale, setLocale, syncLocaleFromServer, t, localeMeta],
    );

    return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
}

export function useI18n() {
    const ctx = useContext(I18nContext);

    if (!ctx) {
        throw new Error('useI18n must be used within I18nProvider');
    }

    return ctx;
}

export function useT() {
    return useI18n().t;
}
