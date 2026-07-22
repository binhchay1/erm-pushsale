import {
    PUSHSALE_CSS_MODULES,
    PUSHSALE_VENDOR_STYLES,
    assetMatchersForCssFile,
    isPushsaleRuntimeStylesheet,
} from '@/lib/pushsaleStyleRegistry';

function ensureLink(href, id, layer = 'vendor') {
    const existing = document.getElementById(id) || document.querySelector(`link[href="${href}"]`);
    if (existing) {
        existing.id ||= id;
        existing.dataset.pushsaleShell = '1';
        existing.dataset.pushsaleLayer = layer;
        return Promise.resolve();
    }

    return new Promise((resolve) => {
        const link = document.createElement('link');
        link.id = id;
        link.rel = 'stylesheet';
        link.href = href;
        link.dataset.pushsaleShell = '1';
        link.dataset.pushsaleLayer = layer;
        link.addEventListener('load', resolve, { once: true });
        // Do not leave the application blank when one optional vendor asset is unavailable.
        link.addEventListener('error', resolve, { once: true });
        document.head.appendChild(link);
    });
}

function hasCompiledStyle(file) {
    const links = [...document.querySelectorAll('link[rel="stylesheet"]')];
    const matchers = assetMatchersForCssFile(file);

    return links.some((link) => {
        const href = link.getAttribute('href') ?? '';
        return matchers.some((pattern) => href.includes(pattern));
    });
}

async function ensureCompiledPushsaleStyles() {
    for (const entry of PUSHSALE_CSS_MODULES) {
        if (!hasCompiledStyle(entry.file)) {
            await entry.load();
        }
    }
}

function moveApplicationStylesAfterVendor() {
    const styles = [...document.querySelectorAll('link[rel="stylesheet"]')].filter((link) => {
        const href = link.getAttribute('href') ?? '';
        return !link.dataset.pushsaleLayer?.includes('vendor') && isPushsaleRuntimeStylesheet(href);
    });

    styles.forEach((link) => document.head.appendChild(link));
}

export async function ensurePushsaleStyles() {
    await Promise.all([
        ...PUSHSALE_VENDOR_STYLES.map(({ href, id, layer }) => ensureLink(href, id, layer)),
        ensureCompiledPushsaleStyles(),
    ]);

    // AdminLTE/Bootstrap provide the base primitives; scoped React/legacy page CSS must
    // remain last in the cascade. The order itself is governed only by pushsaleStyleRegistry.
    moveApplicationStylesAfterVendor();
    document.documentElement.dataset.pushsaleStylesReady = '1';
}
