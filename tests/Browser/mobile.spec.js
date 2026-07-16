import { expect, test } from '@playwright/test';
import { assistantPanel, openSearch } from './helpers.js';

test('mobile reader searches, asks, and reads an answer full-screen', async ({ page }) => {
    await page.goto('/docs/announcements');
    const dialog = await openSearch(page);
    await dialog.getByRole('textbox', { name: 'Search documentation' }).fill('How do I add a video?');
    await dialog.getByRole('button', { name: /Ask Assistant about/ }).click();

    const panel = page.locator(assistantPanel);
    await expect(panel.getByRole('article', { name: 'Assistant answer' })).toContainText('Add a video');
    await expect.poll(async () => (await panel.boundingBox())?.x).toBe(0);
    await expect.poll(async () => (await panel.boundingBox())?.width).toBe(390);
});
