import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    timeout: 30_000,
    expect: {
        timeout: 7_500,
    },
    outputDir: 'test-results',
    reporter: process.env.CI
        ? [['github'], ['html', { open: 'never', outputFolder: 'playwright-report' }]]
        : [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        reducedMotion: 'reduce',
        screenshot: 'only-on-failure',
        trace: 'on-first-retry',
    },
    webServer: {
        command: 'composer serve',
        url: 'http://127.0.0.1:8000/docs',
        timeout: 120_000,
        reuseExistingServer: false,
    },
    projects: [
        {
            name: 'desktop-chromium',
            testIgnore: /mobile\.spec\.js/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 1280, height: 800 },
            },
        },
        {
            name: 'mobile-chromium',
            testMatch: /mobile\.spec\.js/,
            use: {
                ...devices['Pixel 5'],
                viewport: { width: 390, height: 844 },
                hasTouch: true,
            },
        },
    ],
});
