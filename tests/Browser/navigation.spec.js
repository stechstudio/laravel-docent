import { expect, test } from '@playwright/test';

/*
 * Soft navigation: docs-to-docs link clicks swap content in place instead of
 * full page loads. A window property planted before clicking proves the
 * document was never torn down.
 */

const plantMarker = (page) => page.evaluate(() => { window.__docentMarker = true; });
const hasMarker = (page) => page.evaluate(() => window.__docentMarker === true);
const sidebar = (page) => page.locator('.docent-sidebar .docent-nav-sections');

test('navigates between docs pages without a full page load', async ({ page }) => {
    await page.goto('/docs/getting-started/introduction');
    await plantMarker(page);

    await sidebar(page).getByRole('link', { name: 'Quickstart', exact: true }).click();

    await expect(page).toHaveURL(/\/docs\/getting-started\/quickstart$/);
    await expect(page.locator('main h1').first()).toHaveText('Quickstart');
    await expect(page).toHaveTitle(/Quickstart/);
    expect(await hasMarker(page)).toBe(true);
});

test('keeps a manually opened group expanded across navigation', async ({ page }) => {
    await page.goto('/docs/getting-started/introduction');
    await plantMarker(page);

    // Troubleshooting has no index.md, so its whole header row is the toggle.
    const toggle = sidebar(page).getByRole('button', { name: 'Troubleshooting', exact: true });
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    await sidebar(page).getByRole('link', { name: 'Quickstart', exact: true }).click();
    await expect(page).toHaveURL(/\/docs\/getting-started\/quickstart$/);

    await expect(sidebar(page).getByRole('button', { name: 'Troubleshooting', exact: true }))
        .toHaveAttribute('aria-expanded', 'true');
    await expect(sidebar(page).getByRole('link', { name: 'Stuck Journals', exact: true })).toBeVisible();
    expect(await hasMarker(page)).toBe(true);
});

test('expands the group a navigated-to page belongs to', async ({ page }) => {
    await page.goto('/docs/getting-started/introduction');
    await plantMarker(page);

    // Deploy is page-backed: the header label navigates, the chevron toggles.
    await sidebar(page).getByRole('link', { name: 'Deploy', exact: true }).click();

    await expect(page).toHaveURL(/\/docs\/guides\/deploy$/);
    await expect(sidebar(page).getByRole('link', { name: 'Deploy', exact: true }))
        .toHaveAttribute('aria-current', 'page');
    await expect(sidebar(page).getByRole('button', { name: 'Toggle Deploy', exact: true }))
        .toHaveAttribute('aria-expanded', 'true');
    await expect(sidebar(page).getByRole('link', { name: 'Production Checklist', exact: true })).toBeVisible();
    expect(await hasMarker(page)).toBe(true);
});

test('handles back and forward without a full reload', async ({ page }) => {
    await page.goto('/docs/getting-started/introduction');
    await plantMarker(page);

    await sidebar(page).getByRole('link', { name: 'Quickstart', exact: true }).click();
    await expect(page).toHaveURL(/\/docs\/getting-started\/quickstart$/);

    await page.goBack();
    await expect(page).toHaveURL(/\/docs\/getting-started\/introduction$/);
    await expect(page.locator('main h1').first()).toHaveText('Introduction');
    expect(await hasMarker(page)).toBe(true);

    await page.goForward();
    await expect(page).toHaveURL(/\/docs\/getting-started\/quickstart$/);
    await expect(page.locator('main h1').first()).toHaveText('Quickstart');
    expect(await hasMarker(page)).toBe(true);
});

test('switches sections softly and reshapes the sidebar', async ({ page }) => {
    await page.goto('/docs/getting-started/introduction');
    await plantMarker(page);

    await page.getByRole('navigation', { name: 'Documentation sections' })
        .getByRole('link', { name: 'Billing', exact: true }).click();

    await expect(page).toHaveURL(/\/docs\/billing/);
    await expect(sidebar(page).getByRole('link', { name: 'Payment Methods', exact: true })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Documentation sections' })
        .getByRole('link', { name: 'Billing', exact: true })).toHaveAttribute('aria-current', 'page');
    expect(await hasMarker(page)).toBe(true);
});

test('updates the sidebar active state on the arrived-at page', async ({ page }) => {
    await page.goto('/docs/getting-started/introduction');
    await plantMarker(page);

    await sidebar(page).getByRole('link', { name: 'Quickstart', exact: true }).click();
    await expect(page).toHaveURL(/\/docs\/getting-started\/quickstart$/);

    await expect(sidebar(page).getByRole('link', { name: 'Quickstart', exact: true }))
        .toHaveAttribute('aria-current', 'page');
    await expect(sidebar(page).getByRole('link', { name: 'Introduction', exact: true }))
        .not.toHaveAttribute('aria-current', 'page');
    expect(await hasMarker(page)).toBe(true);
});
