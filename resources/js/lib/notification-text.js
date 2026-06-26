function customerLine(t, data) {
    const name = data?.customer_name || t('notifications.items.lead.guest');
    const phone = data?.customer_phone ?? '';

    return phone ? `${name} · ${phone}` : name;
}

function pickLocale(map, locale, source) {
    if (!map || typeof map !== 'object') {
        return null;
    }

    return map[locale] ?? (source ? map[source] : null) ?? Object.values(map)[0] ?? null;
}

/** Resolve notification title/message from structured `data` + active locale. */
export function getNotificationText(notification, t, locale = 'vi') {
    const data = notification?.data;
    const type = notification?.type;

    if (data && typeof data === 'object' && data.variant === 'free_text') {
        const source = data.source;
        return {
            title: pickLocale(data.title, locale, source) ?? notification?.title ?? '',
            message: pickLocale(data.message, locale, source) ?? notification?.message ?? '',
        };
    }

    if (data && typeof data === 'object' && type) {
        switch (type) {
            case 'lead': {
                if (data.variant === 'manual') {
                    return {
                        title: t('notifications.items.lead.manual_title'),
                        message: customerLine(t, data),
                    };
                }

                if (data.variant === 'landing' || data.campaign_name) {
                    return {
                        title: t('notifications.items.lead.landing_title', {
                            name: data.campaign_name ?? '',
                        }),
                        message: customerLine(t, data),
                    };
                }

                return {
                    title: t('notifications.items.lead.platform_title', {
                        platform: data.platform ?? '',
                    }),
                    message: customerLine(t, data),
                };
            }
            case 'landing_approval':
                return {
                    title: t('notifications.items.landing_approval.title', {
                        name: data.campaign_name ?? '',
                    }),
                    message: t('notifications.items.landing_approval.message', {
                        creator: data.creator ?? '',
                    }),
                };
            case 'landing_approved':
                return {
                    title: t('notifications.items.landing_approved.title', {
                        name: data.campaign_name ?? '',
                    }),
                    message: t('notifications.items.landing_approved.message'),
                };
            default:
                break;
        }
    }

    return {
        title: notification?.title ?? '',
        message: notification?.message ?? '',
    };
}
