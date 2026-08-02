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
    '.psm-table-scroll',
    '.psr-table-scroll',
    '.ps-sale-table-wrap',
    '.ps-wh-table-shell',
    '.ps-acc-table-wrap',
    '.ps-table-wrap',
    '.ps-op-table-wrap',
    '.ps-system-table-wrap',
    '.ps-login-table-wrap',
    '.ps-ecommerce-table-wrap',
    '.ps-wh-menu-report-table-wrap',
    '.ps85-table-wrap',
    '.ps-sales-leader-table-wrap',
    '.ps-operation-conversion-table-wrap',
    '.ps-sale-work-table-wrap',
    '.ps-revenue-detail-scroll',
    '.ps-sales-revenue-scroll',
    '.ps-sales-revenue-v2-scroll',
    '.ps-marketing-upsale-scroll',
    '.ps-marketing-work-scroll',
    '.ps-closing-summary-scroll',
    '.ps-product-conversion-scroll',
    '.ps-system-business-scroll',
    '.ps-warehouse-pending-table',
    '.ps-power-matrix-wrap',
    '.ps-power-panel-table',
    '.ps-facebook-table-scroll',
    '.pslc-table-scroll',
    '.ps-pc-table-wrap',
    '.psdd-product-panel',
    '.psdd-sale-panel',
    '.ps-lead-import-upload',
    '[data-pushsale-table="true"]',
    '[data-pushsale-mobile-table-shell="1"]',
    '[class*="table-scroll"]',
    '[class*="table-wrap"]',
    '[class*="table-shell"]',
    '[class*="table-frame"]',
    '.dragscroll1',
    '.tableFixHead',
].join(', ');

let tablePanningInstalled = false;


function visibleColumnCount(table) {
    const rows = [
        ...(table.tHead?.rows ? [...table.tHead.rows] : []),
        ...(table.tBodies?.[0]?.rows ? [...table.tBodies[0].rows].slice(0, 3) : []),
        ...(table.rows ? [...table.rows].slice(0, 3) : []),
    ];

    return Math.max(1, ...rows.map((row) => [...row.cells].reduce((total, cell) => total + (Number(cell.colSpan) || 1), 0)));
}

function preferredColumnWidth(table) {
    const text = String(table.textContent ?? '').toLocaleLowerCase('vi');
    const isDenseReport = table.closest?.('.ps-report-page, .pushsale-kind-report, [data-page-code^="4."], [data-page-code^="6."], [data-page-code^="8."]')
        || table.className?.toString?.().includes('report')
        || text.includes('doanh số')
        || text.includes('tỷ lệ')
        || text.includes('đối soát');
    const hasLongContent = [...table.querySelectorAll('th, td')].some((cell) => String(cell.textContent ?? '').trim().length > 28);

    if (isDenseReport) return hasLongContent ? 136 : 120;
    if (hasLongContent) return 128;
    return 112;
}

function markResponsiveTable(table) {
    if (!table || table.dataset.pushsaleResponsiveTable === '1') return;

    const columnCount = visibleColumnCount(table);
    const formLike = columnCount <= 3 && Boolean(table.querySelector('input, select, textarea'));
    const colWidth = formLike ? 160 : preferredColumnWidth(table);
    const minWidth = Math.min(formLike ? 980 : 3600, Math.max(formLike ? 520 : 760, columnCount * colWidth));

    table.dataset.pushsaleResponsiveTable = '1';
    table.style.setProperty('--pushsale-table-min-width', `${minWidth}px`);
    table.classList.add('pushsale-responsive-table');
    if (formLike) table.dataset.pushsaleFormTable = '1';

    const currentShell = table.closest(PUSHSALE_TABLE_SCROLL_SELECTOR);
    if (currentShell) {
        currentShell.dataset.pushsaleTable = 'true';
        currentShell.dataset.pushsaleMobileTableShell = '1';
        currentShell.style.setProperty('--pushsale-table-min-width', `${minWidth}px`);
        return;
    }

    const parent = table.parentElement;
    if (!parent || parent.dataset.pushsaleTableWrapped === '1') return;
    if (parent.matches?.('thead, tbody, tfoot, tr')) return;

    const wrapper = document.createElement('div');
    wrapper.className = 'pushsale-template-table-scroll';
    wrapper.dataset.pushsaleTable = 'true';
    wrapper.dataset.pushsaleMobileTableShell = '1';
    wrapper.dataset.pushsaleTableWrapped = '1';
    wrapper.style.setProperty('--pushsale-table-min-width', `${minWidth}px`);
    parent.insertBefore(wrapper, table);
    wrapper.appendChild(table);
}

let responsiveTableGuardInstalled = false;
let responsiveTableGuardRaf = 0;

function runPushsaleResponsiveTableGuard() {
    responsiveTableGuardRaf = 0;
    if (typeof document === 'undefined') return;

    const tables = document.querySelectorAll([
        'body.pushsale-app-body .ps-page-shell table',
        'body.pushsale-app-body .pushsale-page table',
        'body.pushsale-app-body .ps-adminlte-page table',
        'body.pushsale-app-body .ps-report-page table',
        'body.pushsale-app-body .ps-feature-page table',
        'body.pushsale-app-body .pushsale-template-host table',
        'body.pushsale-app-body .ps-dialog-surface table',
        'body.pushsale-app-body .pushsale-editor-dialog table',
        'body.pushsale-app-body .modal-dialog table',
        'body.pushsale-app-body [role="dialog"] table',
    ].join(', '));

    tables.forEach(markResponsiveTable);
}

function schedulePushsaleResponsiveTableGuard() {
    if (responsiveTableGuardRaf || typeof window === 'undefined') return;
    responsiveTableGuardRaf = window.requestAnimationFrame(runPushsaleResponsiveTableGuard);
}

function installPushsaleResponsiveTableGuard() {
    if (responsiveTableGuardInstalled || typeof document === 'undefined') return;
    responsiveTableGuardInstalled = true;

    schedulePushsaleResponsiveTableGuard();

    const observer = new MutationObserver((mutations) => {
        if (!mutations.some((mutation) => mutation.addedNodes.length > 0)) return;
        schedulePushsaleResponsiveTableGuard();
    });
    observer.observe(document.body, { childList: true, subtree: true });

    document.addEventListener('inertia:finish', schedulePushsaleResponsiveTableGuard);
    window.addEventListener('resize', schedulePushsaleResponsiveTableGuard, { passive: true });
}

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
    installPushsaleResponsiveTableGuard();
    installPushsaleTablePanning();
    document.documentElement.dataset.pushsaleStylesReady = '1';
}
