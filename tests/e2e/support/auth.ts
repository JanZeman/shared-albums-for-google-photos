import { expect, type Page } from '@playwright/test';

export const ADMIN_USER = process.env.JZSA_E2E_ADMIN_USER ?? 'dev';
export const ADMIN_PASS = process.env.JZSA_E2E_ADMIN_PASS ?? 'test123';
export const CONNECTED_USER = process.env.JZSA_E2E_CONNECTED_USER ?? ADMIN_USER;
export const CONNECTED_PASS = process.env.JZSA_E2E_CONNECTED_PASS ?? ADMIN_PASS;
export const DISCONNECTED_USER = process.env.JZSA_E2E_DISCONNECTED_USER ?? 'testuser-noc';
export const DISCONNECTED_PASS = process.env.JZSA_E2E_DISCONNECTED_PASS ?? 'testpass123';

export async function loginAs(page: Page, user: string, pass: string): Promise<void> {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', user);
    await page.fill('#user_pass', pass);

    await Promise.all([
        page.waitForURL(/\/wp-admin\//, { timeout: 20_000 }),
        page.click('#wp-submit'),
    ]);

    await expect(page.locator('#wpadminbar')).toBeAttached({ timeout: 10_000 });
}

export async function loginAsAdmin(page: Page): Promise<void> {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
}
