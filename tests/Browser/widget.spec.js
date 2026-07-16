import { expect, test } from '@playwright/test';
import { openWidget } from './helpers.js';

test.beforeEach(async ({ page }) => {
    await page.goto('/');
});

test('launcher opens the widget and article navigation stays in the panel', async ({ page }) => {
    const frame = await openWidget(page);
    await frame.getByRole('link', { name: 'Quickstart' }).first().click();

    await expect.poll(() => page.frames().find((candidate) => candidate.parentFrame())?.url())
        .toMatch(/\/docs\/_widget\/getting-started\/quickstart$/);
    await expect(page).toHaveURL(/\/$/);
    await frame.getByRole('link', { name: 'All help' }).click();
    await expect.poll(() => page.frames().find((candidate) => candidate.parentFrame())?.url())
        .toMatch(/\/docs\/_widget$/);
});

test('widget search hands off in-panel, Back returns, and citations keep widget URLs', async ({ page }) => {
    const frame = await openWidget(page);
    const search = frame.getByRole('searchbox', { name: 'Search documentation' });
    await search.fill('How do I add a video?');
    await frame.getByRole('button', { name: 'Ask Assistant', exact: true }).click();

    const assistant = frame.locator('[data-docent-assistant-panel]');
    await expect(assistant).toBeVisible();
    await expect(assistant).toHaveAttribute('role', 'region');
    const answer = assistant.getByRole('article', { name: 'Assistant answer' });
    await expect(answer).toContainText('Add a video');
    await expect(answer.getByRole('link', { name: 'Content Components' })).toHaveAttribute('href', /\/docs\/_widget\//);

    await frame.getByRole('button', { name: 'Back to help' }).click();
    await expect(assistant).toBeHidden();
    await expect(search).toBeFocused();
});
