const PUSHSALE_VENDOR_STYLES = [
    {
        id: 'pushsale-bootstrap',
        hrefs: [
            '/vendor/adminlte2/bootstrap/css/bootstrap.min.css',
            'https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css',
        ],
    },
    {
        id: 'pushsale-font-awesome',
        hrefs: [
            '/vendor/font-awesome/css/font-awesome.min.css',
            'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css',
        ],
    },
    {
        id: 'pushsale-adminlte',
        hrefs: [
            '/vendor/adminlte2/dist/css/AdminLTE.min.css',
            'https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/AdminLTE.min.css',
        ],
    },
    {
        id: 'pushsale-adminlte-skin',
        hrefs: [
            '/vendor/adminlte2/dist/css/skins/skin-blue-light.min.css',
            'https://cdnjs.cloudflare.com/ajax/libs/admin-lte/2.4.18/css/skins/skin-blue-light.min.css',
        ],
    },
    {
        id: 'pushsale-select2',
        hrefs: [
            '/vendor/adminlte2/plugins/select2/select2.min.css',
            'https://cdnjs.cloudflare.com/ajax/libs/select2/3.5.4/select2.min.css',
        ],
    },
    {
        id: 'pushsale-datepicker',
        hrefs: [
            '/vendor/adminlte2/plugins/datepicker/datepicker3.css',
            'https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker3.min.css',
        ],
    },
];

function ensureSingleLink(href, id) {
    const existing = document.getElementById(id) || document.querySelector(`link[href="${href}"]`);
    if (existing) {
        existing.id ||= id;
        existing.dataset.pushsaleShell ||= '1';
        return Promise.resolve(true);
    }

    return new Promise((resolve) => {
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.pushsaleShell = '1';
        link.addEventListener('load', () => resolve(true), { once: true });
        link.addEventListener('error', () => {
            link.remove();
            resolve(false);
        }, { once: true });
        document.head.appendChild(link);
    });
}

async function ensureFirstAvailableLink(style) {
    for (const href of style.hrefs) {
        const loaded = await ensureSingleLink(href, style.id);
        if (loaded) return true;
    }
    return false;
}

async function ensureCompiledPushsaleStyles() {
    const links = [...document.querySelectorAll('link[rel="stylesheet"]')];
    const hasBasePushsale = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-') || href.includes('/resources/css/pushsale.css');
    });
    const hasV67Parity = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-parity-v67-') || href.includes('/resources/css/pushsale-parity-v67.css');
    });
    const hasV68Parity = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-parity-v68-') || href.includes('/resources/css/pushsale-parity-v68.css');
    });

    if (!hasBasePushsale) {
        await import('../../css/pushsale.css');
    }

    if (!hasV67Parity) {
        await import('../../css/pushsale-parity-v67.css');
    }

    // V68 is intentionally last: it restores the local vendor asset contract removed
    // around commit 6b722cf without rolling back newer React/business work.
    if (!hasV68Parity) {
        await import('../../css/pushsale-parity-v68.css');
    }
}

function moveApplicationStylesAfterVendor() {
    const styles = [...document.querySelectorAll('link[rel="stylesheet"]')].filter((link) => {
        const href = link.getAttribute('href') ?? '';
        return !link.dataset.pushsaleShell
            && (
                href.includes('/build/assets/app-')
                || href.includes('/build/assets/pushsale-')
                || href.includes('/build/assets/pushsale-parity-v66-')
                || href.includes('/build/assets/pushsale-parity-v67-')
                || href.includes('/build/assets/pushsale-parity-v68-')
                || href.includes('/resources/css/app.css')
                || href.includes('/resources/css/pushsale.css')
                || href.includes('/resources/css/pushsale-parity-v66.css')
                || href.includes('/resources/css/pushsale-parity-v67.css')
                || href.includes('/resources/css/pushsale-parity-v68.css')
            );
    });

    styles.forEach((link) => document.head.appendChild(link));
}

export async function ensurePushsaleStyles() {
    for (const style of PUSHSALE_VENDOR_STYLES) {
        await ensureFirstAvailableLink(style);
    }

    await ensureCompiledPushsaleStyles();

    // The captured Pushsale shell relies on Bootstrap 3 + AdminLTE 2 + FA4 first,
    // then React compiled CSS as the final layer.
    moveApplicationStylesAfterVendor();
    document.documentElement.dataset.pushsaleStylesReady = '1';
}
