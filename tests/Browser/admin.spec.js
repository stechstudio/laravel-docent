import { expect, test } from '@playwright/test';
import { expectNoSeriousAxeViolations, loginAs, logout } from './helpers.js';

test('member access is forbidden', async ({ page }) => {
    await loginAs(page, 'member');
    const response = await page.goto('/docs/admin');

    expect(response.status()).toBe(403);
    await logout(page);
});

test('an admin authors, publishes, and reads an editable page', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/docs/admin');
    await expect(page.getByText('Select a page to edit')).toBeVisible();

    const sidebar = page.locator('.dax-sidebar');
    await sidebar.getByRole('button', { name: 'New page' }).click();
    await sidebar.getByPlaceholder('slug (e.g. guides/intro)').fill('browser-regression');
    await sidebar.getByPlaceholder('Title').fill('Browser Regression');
    await sidebar.getByRole('button', { name: 'Create' }).click();

    const editor = page.locator('.dax-editor .ProseMirror');
    await expect(editor).toBeVisible();
    await editor.fill('Published from the deterministic browser suite.');
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByText('Draft saved.')).toBeVisible();
    await page.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(page.getByText('Page published.')).toBeVisible();

    await page.goto('/docs/browser-regression');
    await expect(page.getByRole('heading', { level: 1, name: 'Browser Regression' })).toBeVisible();
    await expect(page.getByText('Published from the deterministic browser suite.')).toBeVisible();
});

test('admin editor has no serious or critical accessibility violations', async ({ page }) => {
    await loginAs(page, 'admin');
    await page.goto('/docs/admin');
    await page.getByRole('button', { name: 'Announcements' }).click();
    await expect(page.getByRole('textbox', { name: 'Page title' })).toHaveValue('Announcements');

    await expectNoSeriousAxeViolations(page, {
        include: '#docent-admin',
        exclude: [
            '.dax-tree-item.is-active .dax-tree-title',
            '.dax-btn-primary',
        ],
    });
    await logout(page);
});
