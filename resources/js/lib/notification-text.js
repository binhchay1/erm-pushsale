function customerLine(t, data) {
    const name = data?.customer_name || t('notifications.items.lead.guest');
    const phone = data?.customer_phone ?? '';

    return phone ? `${name} · ${phone}` : name;
}

/** Resolve notification title/message from structured `data` + active locale. */
export function getNotificationText(notification, t) {
    const data = notification?.data;
    const type = notification?.type;

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
