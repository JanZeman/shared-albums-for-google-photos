import { type Page } from '@playwright/test';

export async function gotoFixture(page: Page, url: string): Promise<void> {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
}
