import { expect, test } from '@playwright/test';
import { assistantPanel, askQuestion, delayNextAnswer, openAssistant } from './helpers.js';

test.beforeEach(async ({ page }) => {
    await page.goto('/docs/announcements');
});

test('Escape closes the topmost Assistant surface and restores focus', async ({ page }) => {
    const invoker = page.getByRole('button', { name: 'Open Assistant' });
    await invoker.focus();
    const panel = await openAssistant(page);
    await expect(panel.getByText('What can we help you find?')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(panel).toBeHidden();
    await expect(invoker).toBeFocused();
});

test('answers stream, render citations, remember follow-ups, and accept feedback', async ({ page }) => {
    const panel = await openAssistant(page);
    await delayNextAnswer(page);

    const composer = panel.getByRole('textbox', { name: 'Ask the Assistant' });
    await composer.fill('How do I add a video?');
    await composer.press('Enter');
    await expect(panel.locator('[aria-busy="true"]')).toBeVisible();
    await expect(panel.getByRole('article', { name: 'Assistant is answering' })).toBeVisible();

    const firstAnswer = panel.getByRole('article', { name: 'Assistant answer' });
    await expect(firstAnswer).toContainText('Add a video');
    await expect(firstAnswer.getByRole('link', { name: 'Content components' })).toBeVisible();

    const codeBlock = firstAnswer.locator('[data-docent-assistant-code]');
    const codeScroller = codeBlock.locator('pre');
    const copyCode = codeBlock.getByRole('button', { name: 'Copy code' });
    await codeScroller.locator('code').evaluate((code) => {
        code.textContent += 'x'.repeat(200);
    });
    await expect.poll(() => codeScroller.evaluate((pre) => pre.scrollWidth > pre.clientWidth)).toBe(true);
    const copyPosition = await copyCode.boundingBox();
    await codeScroller.evaluate((pre) => {
        pre.scrollLeft = pre.scrollWidth;
    });
    await expect.poll(() => codeScroller.evaluate((pre) => pre.scrollLeft)).toBeGreaterThan(0);
    expect((await copyCode.boundingBox())?.x).toBeCloseTo(copyPosition?.x ?? 0, 0);

    const followUp = await askQuestion(panel, 'Where are its options?');
    await expect(followUp).toContainText('video options');

    const helpful = followUp.getByRole('button', { name: 'Helpful answer', exact: true });
    await helpful.click();
    await expect(helpful).toHaveAttribute('aria-pressed', 'true');
    await expect(followUp).toContainText('Thanks for the feedback.');
});

test('citation navigation preserves the open transcript', async ({ page }) => {
    const panel = await openAssistant(page);
    await askQuestion(panel, 'How do I add a video?');

    await panel.getByRole('link', { name: 'Content components' }).click();
    await expect(page).toHaveURL(/\/docs\/getting-started\/content-components$/);
    await expect(page.locator(assistantPanel)).toBeVisible();
    await expect(page.locator(assistantPanel).getByText('How do I add a video?', { exact: true })).toBeVisible();
});

test('dark mode applies to the reader and open Assistant', async ({ page }) => {
    const panel = await openAssistant(page);
    await page.getByRole('button', { name: 'Toggle dark mode' }).click();

    await expect(page.locator('html')).toHaveClass(/\bdark\b/);
    await expect(panel).toBeVisible();
});
