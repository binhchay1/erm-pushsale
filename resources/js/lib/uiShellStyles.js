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
    const pending = PUSHSALE_CSS_MODULES.filter((entry) => !hasCompiledStyle(entry.file));
    if (!pending.length) return;
    await Promise.all(pending.map((entry) => entry.load()));
}

let stylesMoved = false;

function moveApplicationStylesAfterVendor() {
    if (stylesMoved) return;

    const styles = [...document.querySelectorAll('link[rel="stylesheet"]')].filter((link) => {
        const href = link.getAttribute('href') ?? '';
        return !link.dataset.pushsaleLayer?.includes('vendor') && isPushsaleRuntimeStylesheet(href);
    });

    styles.forEach((link) => document.head.appendChild(link));
    stylesMoved = true;
}


const PUSHSALE_TABLE_SCROLL_SELECTOR = [
    '.pushsale-template-table-scroll',
    '.table-responsive',
    '.ps-table-scroll',
    '.ps-table-wrap',
    '.ps-op-table-wrap',
    '.ps-system-table-wrap',
    '.ps-login-table-wrap',
    '.ps-ecommerce-table-wrap',
    '.ps-wh-menu-report-table-wrap',
    '[data-pushsale-table="true"]',
    '.dragscroll1',
    '.tableFixHead',
].join(', ');

let tablePanningInstalled = false;

function installPushsaleTablePanning() {
    if (tablePanningInstalled || typeof document === 'undefined') return;
    tablePanningInstalled = true;

    let active = null;
    let suppressClick = false;

    const isInteractiveTarget = (target) => Boolean(target?.closest?.([
        'a',
        'button',
        'input',
        'select',
        'textarea',
        'label',
        '[role="button"]',
        '[contenteditable="true"]',
        '.ps-select',
        '.select2-container',
        '.dropdown-menu',
    ].join(', ')));

    const findScrollableTableShell = (target) => {
        const shell = target?.closest?.(PUSHSALE_TABLE_SCROLL_SELECTOR);
        if (!shell || shell.dataset.pushsalePanDisabled === '1') return null;
        if (shell.scrollWidth <= shell.clientWidth + 1 && shell.scrollHeight <= shell.clientHeight + 1) return null;
        return shell;
    };

    document.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || isInteractiveTarget(event.target)) return;

        const shell = findScrollableTableShell(event.target);
        if (!shell) return;

        active = {
            shell,
            pointerId: event.pointerId,
            startX: event.clientX,
            startY: event.clientY,
            scrollLeft: shell.scrollLeft,
            scrollTop: shell.scrollTop,
            moved: false,
        };

        shell.classList.add('pushsale-table-scroll--panning');
        try { shell.setPointerCapture?.(event.pointerId); } catch { /* noop */ }
    }, { passive: true });

    document.addEventListener('pointermove', (event) => {
        if (!active || active.pointerId !== event.pointerId) return;

        const dx = event.clientX - active.startX;
        const dy = event.clientY - active.startY;
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) active.moved = true;
        if (!active.moved) return;

        active.shell.scrollLeft = active.scrollLeft - dx;
        active.shell.scrollTop = active.scrollTop - dy;
        event.preventDefault();
    }, { passive: false });

    const finishPan = (event) => {
        if (!active || active.pointerId !== event.pointerId) return;

        const { shell, moved } = active;
        try { shell.releasePointerCapture?.(event.pointerId); } catch { /* noop */ }
        shell.classList.remove('pushsale-table-scroll--panning');
        active = null;

        if (moved) suppressClick = true;
    };

    document.addEventListener('pointerup', finishPan, { passive: true });
    document.addEventListener('pointercancel', finishPan, { passive: true });
    document.addEventListener('click', (event) => {
        if (!suppressClick) return;
        suppressClick = false;
        event.preventDefault();
        event.stopPropagation();
    }, true);
}

export async function ensurePushsaleStyles() {
    await Promise.all([
        ...PUSHSALE_VENDOR_STYLES.map(({ href, id, layer }) => ensureLink(href, id, layer)),
        ensureCompiledPushsaleStyles(),
    ]);

    // AdminLTE/Bootstrap provide the base primitives; scoped React/legacy page CSS must
    // remain last in the cascade. The order itself is governed only by pushsaleStyleRegistry.
    moveApplicationStylesAfterVendor();
    installPushsaleTablePanning();
    document.documentElement.dataset.pushsaleStylesReady = '1';
}
