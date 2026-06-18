import { usePage } from '@inertiajs/react';

import vi from '@/i18n/locales/vi/labels';
import en from '@/i18n/locales/en/labels';
import { useI18n } from '@/providers/I18nProvider';

const BY_LOCALE = { vi, en };

/** Enum / status labels — always follow active client locale. */
export function useLabels() {
    const { locale } = useI18n();

    return BY_LOCALE[locale] ?? BY_LOCALE.vi;
}

export function useRoleLabel(role) {
    const labels = useLabels();

    return role ? labels.user_role?.[role] ?? role : '';
}

export function useOrgLevelLabel(level) {
    const labels = useLabels();

    return level ? labels.org_level?.[level] ?? level : null;
}

/** @deprecated Use useRoleLabel — kept for components still reading auth.role_label */
export function useAuthRoleLabel() {
    const { auth } = usePage().props;

    return useRoleLabel(auth.user?.role);
}
