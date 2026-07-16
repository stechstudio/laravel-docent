import { expect, test } from '@playwright/test';
import {
    assistantPanel,
    expectNoSeriousAxeViolations,
    openSearch,
    openWidget,
    searchDialog,
} from './helpers.js';

test('reader headings are ordered and the page passes axe', async ({ page }) => {
    await page.goto('/docs/announcements');
    const main = page.locator('main');
    const levels = await main.locator('h1, h2, h3, h4, h5, h6').evaluateAll((headings) => (
        headings.map((heading) => Number(heading.tagName.slice(1)))
    ));

    expect(levels.filter((level) => level === 1)).toHaveLength(1);
    for (let index = 1; index < levels.length; index += 1) {
        expect(levels[index] - levels[index - 1]).toBeLessThanOrEqual(1);
    }
    await expectNoSeriousAxeViolations(page, {
        include: 'main',
        exclude: ['.docent-pagination > a > span:first-child'],
    });
});

test('open search dialog passes axe', async ({ page }) => {
    await page.goto('/docs/announcements');
    await openSearch(page);

    await expectNoSeriousAxeViolations(page, { include: searchDialog });
});

test('completed Assistant passes axe and supports a keyboard-only feedback path', async ({ page }) => {
    await page.goto('/docs/announcements');
    const dialog = await openSearch(page);
    const input = dialog.getByRole('textbox', { name: 'Search documentation' });
    await input.fill('How do I add a video?');
    await expect(dialog.locator('[data-selected="true"]')).toBeVisible();
    await input.press('ArrowUp');
    await input.press('Enter');

    const panel = page.locator(assistantPanel);
    const answer = panel.getByRole('article', { name: 'Assistant answer' });
    await expect(answer).toBeVisible();
    const helpful = answer.getByRole('button', { name: 'Helpful answer', exact: true });
    await helpful.focus();
    await page.keyboard.press('Enter');
    await expect(helpful).toHaveAttribute('aria-pressed', 'true');

    await expectNoSeriousAxeViolations(page, {
        include: assistantPanel,
        exclude: [
            '.docent-assistant-link',
            '[x-text="citation.title"]',
        ],
    });
});

test('widget home passes axe', async ({ page }) => {
    await page.goto('/');
    await openWidget(page);

    await expectNoSeriousAxeViolations(page, {
        include: 'iframe[title="Documentation"]',
        exclude: [
            ['iframe[title="Documentation"]', '.tracking-\\[0\\.16em\\]'],
            ['iframe[title="Documentation"]', '.docent-widget-nav .text-slate-400'],
        ],
    });
});
