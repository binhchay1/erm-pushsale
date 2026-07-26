import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const ACTIVE_MENU_STORAGE_KEY = 'pushsale-active-menu-code';
const FLYOUT_CLOSE_DELAY_MS = 160;
const HEADER_OFFSET = 50;
const ROW_HEIGHT = 40;

export const DEFAULT_MENU_ICONS = {
    1: 'cog',
    2: 'trophy',
    3: 'user',
    4: 'tty',
    5: 'tags',
    6: 'calculator',
    7: 'user-secret',
    8: 'dashboard',
    9: 'credit-card',
};

export function cleanPath(url = '') {
    const path = String(url).split('?')[0].split('#')[0] || '/';
    return path.length > 1 ? path.replace(/\/+$/, '') : path;
}

export function flattenLeaves(items, prefix = 'root') {
    const leaves = [];
    items.forEach((item, index) => {
        const key = `${prefix}.${index}`;
        const children = item.children ?? [];
        if (children.length) leaves.push(...flattenLeaves(children, key));
        else if (item.url && !item.disabled) leaves.push({ item, key });
    });
    return leaves;
}

export function resolveActiveKey(navigation, currentUrl, activeMenuCode, rememberedMenuCode) {
    const leaves = flattenLeaves(navigation);
    if (activeMenuCode) {
        const byCode = leaves.find(({ item }) => String(item.code ?? '') === String(activeMenuCode));
        if (byCode) return byCode.key;
    }

    const currentPath = cleanPath(currentUrl);
    if (rememberedMenuCode) {
        const remembered = leaves.find(({ item }) =>
            String(item.code ?? '') === String(rememberedMenuCode) && cleanPath(item.url) === currentPath,
        );
        if (remembered) return remembered.key;
    }

    return leaves.find(({ item }) => cleanPath(item.url) === currentPath)?.key ?? null;
}

export function keyContains(activeKey, key) {
    return Boolean(activeKey && (activeKey === key || activeKey.startsWith(`${key}.`)));
}

export function menuNumber(title = '') {
    const match = String(title).match(/^(\d+)\./);
    return match ? Number(match[1]) : null;
}

function readRememberedMenuCode() {
    if (typeof window === 'undefined') return null;
    return window.sessionStorage.getItem(ACTIVE_MENU_STORAGE_KEY);
}

function measureFlyout(anchorEl, item) {
    const rect = anchorEl.getBoundingClientRect();
    const estimatedHeight = Math.max(ROW_HEIGHT, (item.children?.length ?? 1) * ROW_HEIGHT);
    const maxHeight = Math.max(120, window.innerHeight - HEADER_OFFSET - 8);
    const top = Math.max(
        HEADER_OFFSET,
        Math.min(rect.top, window.innerHeight - Math.min(estimatedHeight, maxHeight) - 8),
    );

    return { top, maxHeight };
}

/**
 * Shared Pushsale sidebar runtime: accordion, L2 hover class, L3 flyout,
 * outside click, Escape, resize, and hover bridge timers.
 */
export function usePushsaleSidebarMenu({ navigation = [], url, activeMenuCode = null, collapsed = true }) {
    const sidebarRef = useRef(null);
    const flyoutTimerRef = useRef(null);

    const [rememberedMenuCode, setRememberedMenuCode] = useState(readRememberedMenuCode);
    const [openRoot, setOpenRoot] = useState(null);
    const [flyout, setFlyout] = useState(null);
    const [hoverSecondKey, setHoverSecondKey] = useState(null);

    const activeKey = useMemo(
        () => resolveActiveKey(navigation, url, activeMenuCode, rememberedMenuCode),
        [activeMenuCode, navigation, rememberedMenuCode, url],
    );

    const activeRootIndex = useMemo(() => {
        if (!activeKey) return null;
        const match = activeKey.match(/^root\.(\d+)/);
        return match ? Number(match[1]) : null;
    }, [activeKey]);

    const clearFlyoutTimer = useCallback(() => {
        if (flyoutTimerRef.current) {
            window.clearTimeout(flyoutTimerRef.current);
            flyoutTimerRef.current = null;
        }
    }, []);

    const blurActiveMenuButton = useCallback(() => {
        if (typeof document === 'undefined') return;
        const active = document.activeElement;
        if (active?.matches?.('.pushsale-main-sidebar .li2 > button.pushsale-menu-link')) {
            active.blur();
        }
    }, []);

    const closeFlyout = useCallback(() => {
        clearFlyoutTimer();
        blurActiveMenuButton();
        setFlyout(null);
        setHoverSecondKey(null);
    }, [blurActiveMenuButton, clearFlyoutTimer]);

    const scheduleFlyoutClose = useCallback((delay = FLYOUT_CLOSE_DELAY_MS) => {
        clearFlyoutTimer();
        flyoutTimerRef.current = window.setTimeout(() => {
            blurActiveMenuButton();
            setFlyout(null);
            setHoverSecondKey(null);
            flyoutTimerRef.current = null;
        }, delay);
    }, [blurActiveMenuButton, clearFlyoutTimer]);

    const openFlyoutFor = useCallback((anchorEl, item, key) => {
        clearFlyoutTimer();
        const position = measureFlyout(anchorEl, item);
        setFlyout({ item, key, ...position });
        setHoverSecondKey(key);
    }, [clearFlyoutTimer]);

    const toggleFlyout = useCallback((anchorEl, item, key) => {
        clearFlyoutTimer();
        setFlyout((current) => {
            if (current?.key === key) {
                setHoverSecondKey(null);
                return null;
            }
            const position = measureFlyout(anchorEl, item);
            setHoverSecondKey(key);
            return { item, key, ...position };
        });
    }, [clearFlyoutTimer]);

    const toggleRoot = useCallback((index) => {
        closeFlyout();
        setOpenRoot((current) => (current === index ? null : index));
    }, [closeFlyout]);

    const rememberSelection = useCallback((item) => {
        const code = item?.code ? String(item.code) : null;
        if (!code) return;
        setRememberedMenuCode(code);
        window.sessionStorage.setItem(ACTIVE_MENU_STORAGE_KEY, code);
    }, []);

    const onSecondEnter = useCallback((key, hasGrandchildren) => {
        setHoverSecondKey(key);
        if (!hasGrandchildren) closeFlyout();
    }, [closeFlyout]);

    const onSecondLeave = useCallback((key) => {
        setHoverSecondKey((current) => (current === key ? null : current));
    }, []);

    useEffect(() => {
        if (collapsed) {
            setOpenRoot(null);
            setHoverSecondKey(null);
            closeFlyout();
            return;
        }
        setOpenRoot(activeRootIndex);
    }, [activeRootIndex, closeFlyout, collapsed]);

    useEffect(() => {
        setHoverSecondKey(null);
        closeFlyout();
    }, [closeFlyout, url]);

    useEffect(() => {
        const closeOutside = (event) => {
            const target = event.target;
            if (target.closest?.('.pushsale-third-menu') || target.closest?.('[data-pushsale-second-parent="true"]')) {
                return;
            }
            closeFlyout();
        };
        const closeOnEscape = (event) => {
            if (event.key === 'Escape') closeFlyout();
        };
        const closeOnViewportChange = () => closeFlyout();

        document.addEventListener('mousedown', closeOutside);
        document.addEventListener('touchstart', closeOutside, { passive: true });
        document.addEventListener('keydown', closeOnEscape);
        window.addEventListener('resize', closeOnViewportChange);

        return () => {
            document.removeEventListener('mousedown', closeOutside);
            document.removeEventListener('touchstart', closeOutside);
            document.removeEventListener('keydown', closeOnEscape);
            window.removeEventListener('resize', closeOnViewportChange);
            clearFlyoutTimer();
        };
    }, [clearFlyoutTimer, closeFlyout]);

    return {
        sidebarRef,
        activeKey,
        openRoot,
        flyout,
        hoverSecondKey,
        toggleRoot,
        toggleFlyout,
        openFlyoutFor,
        closeFlyout,
        scheduleFlyoutClose,
        clearFlyoutTimer,
        rememberSelection,
        onSecondEnter,
        onSecondLeave,
    };
}
