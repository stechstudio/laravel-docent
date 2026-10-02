import { expect, test } from '@playwright/test';
import { openWidget } from './helpers.js';

// Livewire's wire:navigate and Turbo replace <body> on each page change, and
// Back restores a saved copy of the old body that still holds the launcher and
// panel. These helpers stand in for both.
function swapBody(page, { route = null, widget = true } = {}) {
    return page.evaluate(({ route, widget }) => {
        const next = document.body.cloneNode(true);
        const config = next.querySelector('[data-docent-widget-config]');

        if (!widget) {
            next.querySelectorAll('[data-docent-launcher], [data-docent-panel], [data-docent-widget-config]')
                .forEach((node) => node.remove());
        } else if (route !== null) {
            config.textContent = JSON.stringify({ ...JSON.parse(config.textContent), page: route });
        }

        document.body.replaceWith(next);
    }, { route, widget });
}

// Open and close the widget once so the panel exists alongside the launcher.
test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await openWidget(page);
    await page.keyboard.press('Escape');
    await expect(page.locator('[data-docent-panel]')).toBeHidden();
});

test('restoring a saved body keeps one launcher and one panel', async ({ page }) => {
    for (let press = 0; press < 4; press++) {
        await swapBody(page);
        await expect(page.locator('[data-docent-launcher]')).toHaveCount(1);
        await expect(page.locator('[data-docent-panel]')).toHaveCount(1);
    }

    await openWidget(page);
});

test('a new body moves the widget to the new page context', async ({ page }) => {
    const changes = [];
    await page.exposeFunction('recordPageChange', (detail) => changes.push(detail.page));
    await page.evaluate(() => window.addEventListener('docent:analytics', ({ detail }) => {
        if (detail.event === 'page_context_changed') window.recordPageChange(detail);
    }));

    await swapBody(page, { route: 'workbench.billing.settings' });

    await expect.poll(() => changes).toEqual(['workbench.billing.settings']);
});

test('a body without the widget drops it until one with the widget returns', async ({ page }) => {
    await swapBody(page, { widget: false });
    await expect(page.locator('[data-docent-launcher]')).toHaveCount(0);
    await expect(page.locator('[data-docent-panel]')).toHaveCount(0);

    await page.evaluate(() => {
        const config = document.createElement('script');
        config.type = 'application/json';
        config.dataset.docentWidgetConfig = '';
        config.textContent = '{"page":"dashboard.overview"}';
        const next = document.body.cloneNode(true);
        next.appendChild(config);
        document.body.replaceWith(next);
    });

    await expect(page.locator('[data-docent-launcher]')).toHaveCount(1);
    await openWidget(page);
});
