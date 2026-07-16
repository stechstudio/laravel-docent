import { expect, test } from '@playwright/test';
import { assistantPanel, openSearch, searchDialog } from './helpers.js';

test.beforeEach(async ({ page }) => {
    await page.goto('/docs/announcements');
});

test('search opens from the keyboard, traps focus, and restores its invoker', async ({ page }) => {
    const invoker = page.getByRole('button', { name: 'Search documentation' });
    await invoker.focus();

    const dialog = await openSearch(page);
    const input = dialog.getByRole('textbox', { name: 'Search documentation' });
    await input.fill('video');
    await expect(dialog.locator('[data-selected="true"]')).toBeVisible();

    await input.press('Shift+Tab');
    await expect(dialog.getByRole('button', { name: /Ask Assistant about/ })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(input).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(page.locator(searchDialog)).toBeHidden();
    await expect(invoker).toBeFocused();
});

test('arrow keys select a search result and Enter follows it', async ({ page }) => {
    const dialog = await openSearch(page);
    const input = dialog.getByRole('textbox', { name: 'Search documentation' });
    await input.fill('reconcile bank feed');

    const selected = dialog.locator('[data-selected="true"]');
    await expect(selected).toHaveAttribute('href', /\/docs\//);
    const firstHref = await selected.getAttribute('href');

    await input.press('ArrowDown');
    await expect(selected).not.toHaveAttribute('href', firstHref);
    await input.press('ArrowUp');
    await expect(selected).toHaveAttribute('href', firstHref);
    await input.press('Enter');

    await expect(page).toHaveURL(firstHref);
});

test('search hands a natural question to the Assistant', async ({ page }) => {
    const dialog = await openSearch(page);
    const question = 'How do I add a video?';
    await dialog.getByRole('textbox', { name: 'Search documentation' }).fill(question);
    await dialog.getByRole('button', { name: /Ask Assistant about/ }).click();

    await expect(page.locator(searchDialog)).toBeHidden();
    const panel = page.locator(assistantPanel);
    await expect(panel).toBeVisible();
    await expect(panel.getByText(question, { exact: true })).toBeVisible();
    await expect(panel.getByRole('article', { name: 'Assistant answer' })).toBeVisible();
});
