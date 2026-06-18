import en from '@/i18n/locales/en/index';
import vi from '@/i18n/locales/vi/index';

const DICTS = { vi, en };

let currentLocale = typeof document !== 'undefined'
    ? document.documentElement.lang?.slice(0, 2) || 'vi'
    : 'vi';

function getByPath(obj, path) {
    return path.split('.').reduce((acc, key) => acc?.[key], obj);
}

function interpolate(str, params = {}) {
    if (typeof str !== 'string') {
        return str;
    }

    return str.replace(/:(\w+)/g, (_, key) => (params[key] !== undefined ? String(params[key]) : `:${key}`));
}

export function setTranslateLocale(locale) {
    if (locale) {
        currentLocale = locale;
    }
}

export function translate(key, params, locale = currentLocale) {
    const dict = DICTS[locale] ?? DICTS.vi;
    const value = getByPath(dict, key) ?? getByPath(DICTS.vi, key) ?? key;

    return interpolate(value, params);
}
