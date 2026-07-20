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
    const alreadyLoaded = [...document.querySelectorAll('link[rel="stylesheet"]')].some((link) => {
        const href = link.getAttribute('href') ?? '';
        return href.includes('/build/assets/pushsale-') || href.includes('/resources/css/pushsale.css');
    });
    if (alreadyLoaded) return;

    await import('../../css/pushsale.css');
}

function moveApplicationStylesAfterVendor() {
    const styles = [...document.querySelectorAll('link[rel=\"stylesheet\"]')].filter((link) => {
        const href = link.getAttribute('href') ?? '';
        return !link.dataset.pushsaleShell && (href.includes('/build/assets/app-') || href.includes('/build/assets/pushsale-') || href.includes('/resources/css/app.css') || href.includes('/resources/css/pushsale.css'));
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
