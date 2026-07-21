const PUSHSALE_STYLES = [
    ['/vendor/adminlte2/bootstrap/css/bootstrap.min.css', 'pushsale-bootstrap'],
    ['/vendor/font-awesome/css/font-awesome.min.css', 'pushsale-font-awesome'],
    ['/vendor/adminlte2/dist/css/AdminLTE.min.css', 'pushsale-adminlte'],
    ['/vendor/adminlte2/dist/css/skins/skin-blue-light.min.css', 'pushsale-adminlte-skin'],
    ['/vendor/adminlte2/plugins/select2/select2.min.css', 'pushsale-select2'],
    ['/vendor/adminlte2/plugins/datepicker/datepicker3.css', 'pushsale-datepicker'],
];

function ensureLink(href, id) {
    const existing = document.getElementById(id) || document.querySelector(`link[href="${href}"]`);
    if (existing) {
        existing.id ||= id;
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.pushsaleShell = '1';
        link.addEventListener('load', resolve, { once: true });
        // Do not leave the application blank when one optional vendor asset is unavailable.
        link.addEventListener('error', resolve, { once: true });
        document.head.appendChild(link);
    });
}


async function ensureCompiledPushsaleStyles() {
    const links = [...document.querySelectorAll('link[rel="stylesheet"]')];
    const hasPushsale = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-') || href.includes('/resources/css/pushsale.css');
    });
    const hasV70 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v70-page-polish-') || href.includes('/resources/css/pushsale-v70-page-polish.css');
    });
    const hasV71 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v71-combo-page-') || href.includes('/resources/css/pushsale-v71-combo-page.css');
    });
    const hasV72 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v72-login-history-') || href.includes('/resources/css/pushsale-v72-login-history.css');
    });
    const hasV73 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v73-operation-categories-') || href.includes('/resources/css/pushsale-v73-operation-categories.css');
    });
    const hasV74 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v74-users-frame-toast-') || href.includes('/resources/css/pushsale-v74-users-frame-toast.css');
    });
    const hasV75 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v75-teams-page-') || href.includes('/resources/css/pushsale-v75-teams-page.css');
    });
    const hasV76 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v76-operations-polish-') || href.includes('/resources/css/pushsale-v76-operations-polish.css');
    });
    const hasV77 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v77-accounting-operations-') || href.includes('/resources/css/pushsale-v77-accounting-operations.css');
    });

    const hasV78 = links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-v78-shared-filters-actions-') || href.includes('/resources/css/pushsale-v78-shared-filters-actions.css');
    });

    if (!hasPushsale) {
        await import('../../css/pushsale.css');
    }

    if (!hasV70) {
        await import('../../css/pushsale-v70-page-polish.css');
    }

    if (!hasV71) {
        await import('../../css/pushsale-v71-combo-page.css');
    }

    if (!hasV72) {
        await import('../../css/pushsale-v72-login-history.css');
    }

    if (!hasV73) {
        await import('../../css/pushsale-v73-operation-categories.css');
    }

    if (!hasV74) {
        await import('../../css/pushsale-v74-users-frame-toast.css');
    }

    if (!hasV75) {
        await import('../../css/pushsale-v75-teams-page.css');
    }

    if (!hasV76) {
        await import('../../css/pushsale-v76-operations-polish.css');
    }

    if (!hasV77) {
        await import('../../css/pushsale-v77-accounting-operations.css');
    }

    if (!hasV78) {
        await import('../../css/pushsale-v78-shared-filters-actions.css');
    }
}

function moveApplicationStylesAfterVendor() {
    const styles = [...document.querySelectorAll('link[rel=\"stylesheet\"]')].filter((link) => {
        const href = link.getAttribute('href') ?? '';
        return !link.dataset.pushsaleShell && (href.includes('/build/assets/app-') || href.includes('/build/assets/pushsale-') || href.includes('/resources/css/app.css') || href.includes('/resources/css/pushsale.css') || href.includes('/resources/css/pushsale-v70-page-polish.css') || href.includes('/build/assets/pushsale-v70-page-polish-') || href.includes('/resources/css/pushsale-v71-combo-page.css') || href.includes('/build/assets/pushsale-v71-combo-page-') || href.includes('/resources/css/pushsale-v72-login-history.css') || href.includes('/build/assets/pushsale-v72-login-history-') || href.includes('/resources/css/pushsale-v73-operation-categories.css') || href.includes('/build/assets/pushsale-v73-operation-categories-') || href.includes('/resources/css/pushsale-v74-users-frame-toast.css') || href.includes('/build/assets/pushsale-v74-users-frame-toast-') || href.includes('/resources/css/pushsale-v75-teams-page.css') || href.includes('/build/assets/pushsale-v75-teams-page-') || href.includes('/resources/css/pushsale-v76-operations-polish.css') || href.includes('/build/assets/pushsale-v76-operations-polish-') || href.includes('/resources/css/pushsale-v77-accounting-operations.css') || href.includes('/build/assets/pushsale-v77-accounting-operations-') || href.includes('/resources/css/pushsale-v78-shared-filters-actions.css') || href.includes('/build/assets/pushsale-v78-shared-filters-actions-'));
    });

    styles.forEach((link) => document.head.appendChild(link));
}

export async function ensurePushsaleStyles() {
    await Promise.all([
        ...PUSHSALE_STYLES.map(([href, id]) => ensureLink(href, id)),
        ensureCompiledPushsaleStyles(),
    ]);
    // AdminLTE/Bootstrap provide the base primitives; the scoped React page CSS must
    // remain last in the cascade so each recreated Pushsale screen keeps its exact layout.
    moveApplicationStylesAfterVendor();
    document.documentElement.dataset.pushsaleStylesReady = '1';
}
