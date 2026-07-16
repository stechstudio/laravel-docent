import AxeBuilder from '@axe-core/playwright';
import { expect } from '@playwright/test';

export const searchDialog = '[data-docent-search-dialog]';
export const assistantPanel = '[data-docent-assistant-panel]';

export async function loginAs(page, role) {
    await page.goto(`/demo/login/${role}`);
    await expect(page).toHaveURL(/\/docs(?:\/)?$/);
}

export async function logout(page) {
    await page.goto('/demo/logout');
    await expect(page).toHaveURL(/\/docs(?:\/)?$/);
}

export async function openSearch(page) {
    await page.keyboard.press('ControlOrMeta+KeyK');
    const dialog = page.locator(searchDialog);
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole('textbox', { name: 'Search documentation' })).toBeFocused();

    return dialog;
}

export async function openAssistant(page) {
    await page.keyboard.press('ControlOrMeta+KeyI');
    const panel = page.locator(assistantPanel);
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('textbox', { name: 'Ask the Assistant' })).toBeFocused();

    return panel;
}

export async function askQuestion(scope, question) {
    const composer = scope.getByRole('textbox', { name: 'Ask the Assistant' });
    await composer.fill(question);
    await composer.press('Enter');
    await expect(scope.getByRole('article', { name: 'Assistant answer' }).last()).toBeVisible();

    return scope.getByRole('article', { name: 'Assistant answer' }).last();
}

export async function delayNextAnswer(page) {
    await page.route('**/docs/_ask*', async (route) => {
        await new Promise((resolve) => setTimeout(resolve, 250));
        await route.continue();
    }, { times: 1 });
}

export async function expectNoSeriousAxeViolations(page, { include, exclude = [] } = {}) {
    let scan = new AxeBuilder({ page }).withTags([
        'wcag2a',
        'wcag2aa',
        'wcag21a',
        'wcag21aa',
    ]);

    if (include) scan = scan.include(include);
    for (const selector of exclude) scan = scan.exclude(selector);

    const results = await scan.analyze();
    const violations = results.violations.filter(({ impact }) => impact === 'serious' || impact === 'critical');
    const summary = violations.map(({ id, impact, help, nodes }) => ({
        id,
        impact,
        help,
        targets: nodes.map(({ target }) => target),
    }));

    expect(violations, JSON.stringify(summary, null, 2)).toEqual([]);
}

export async function openWidget(page) {
    const launcher = page.locator('[data-docent-launcher]');
    await expect(launcher).toBeVisible();
    await launcher.click();

    const frame = page.frameLocator('iframe[title="Documentation"]');
    await expect(frame.locator('html[data-docent-widget]')).toBeVisible();
    const search = frame.getByRole('searchbox', { name: 'Search documentation' });
    await expect(search).toBeVisible();
    await expect.poll(() => search.evaluate((element) => Boolean(element._x_model))).toBe(true);

    return frame;
}
