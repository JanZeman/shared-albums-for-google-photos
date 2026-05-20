import { expect, type Page } from '@playwright/test';

export const ADMIN_USER = process.env.JZSA_E2E_ADMIN_USER ?? 'dev';
export const ADMIN_PASS = process.env.JZSA_E2E_ADMIN_PASS ?? 'test123';
export const CONNECTED_USER = process.env.JZSA_E2E_CONNECTED_USER ?? ADMIN_USER;
export const CONNECTED_PASS = process.env.JZSA_E2E_CONNECTED_PASS ?? ADMIN_PASS;
export const DISCONNECTED_USER = process.env.JZSA_E2E_DISCONNECTED_USER ?? 'testuser-noc';
export const DISCONNECTED_PASS = process.env.JZSA_E2E_DISCONNECTED_PASS ?? 'testpass123';

export async function loginAs(page: Page, user: string, pass: string): Promise<void> {
    let lastError: unknown;

    for (let attempt = 1; attempt <= 2; attempt++) {
        try {
            await page.context().clearCookies();
            await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded', timeout: 5_000 });
            await page.fill('#user_login', user);
            await page.fill('#user_pass', pass);

            await Promise.all([
                page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 5_000 }).catch(() => {}),
                page.locator('#loginform').evaluate((form) => (form as HTMLFormElement).submit()),
            ]);

            await page.goto('/wp-admin/', { waitUntil: 'domcontentloaded', timeout: 5_000 });
            await expect(page.locator('#wpadminbar')).toBeAttached({ timeout: 3_000 });
            return;
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError instanceof Error ? lastError : new Error(`Failed to log in as ${user}`);
}

export async function loginAsAdmin(page: Page): Promise<void> {
    await loginAs(page, ADMIN_USER, ADMIN_PASS);
}
