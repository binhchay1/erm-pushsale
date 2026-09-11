import { test as base, expect, type Page } from '@playwright/test';

import { DEMO, loginAs, mockCustomer, logout } from './auth';
import {
    confirmDialog,
    expectSuccessToast,
    fillOrderText,
    openWarehouseFab,
    orderField,
    selectManualDdl,
    selectNativeByLabel,
    selectPushsaleOption,
    waitUiReady,
} from './ui';

type Fixtures = {
    /** Guest context — no storageState from globalSetup. */
    guestPage: Page;
};

/**
 * Extend base test: guestPage clears auth so role-switching demos work.
 * Authenticated smoke still uses globalSetup storageState via default `page`.
 */
export const test = base.extend<Fixtures>({
    guestPage: async ({ browser }, use) => {
        const context = await browser.newContext({
            locale: 'vi-VN',
            storageState: { cookies: [], origins: [] },
            baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8000',
        });
        const page = await context.newPage();
        await use(page);
        await context.close();
    },
});

export {
    expect,
    DEMO,
    loginAs,
    logout,
    mockCustomer,
    waitUiReady,
    expectSuccessToast,
    fillOrderText,
    orderField,
    selectManualDdl,
    selectNativeByLabel,
    selectPushsaleOption,
    confirmDialog,
    openWarehouseFab,
};
