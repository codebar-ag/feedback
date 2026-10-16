import { defineConfig, devices } from '@playwright/test';

const PORT = Number(process.env.WIDGET_E2E_PORT ?? 4173);

/** Widget E2E against the built files (dist/) and the mock API (tests/e2e/server.ts). */
export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 30_000,
  expect: { timeout: 7_000 },
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: 0,
  workers: process.env.CI ? 2 : undefined,
  reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list']],
  use: {
    baseURL: `http://localhost:${PORT}`,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] }, testIgnore: /mobile\.spec/ },
    { name: 'firefox', use: { ...devices['Desktop Firefox'] }, testIgnore: /mobile\.spec/ },
    { name: 'webkit', use: { ...devices['Desktop Safari'] }, testIgnore: /mobile\.spec/ },
    { name: 'mobile-chrome', use: { ...devices['Pixel 7'] }, testMatch: /mobile\.spec/ },
    { name: 'mobile-safari', use: { ...devices['iPhone 15'] }, testMatch: /mobile\.spec/ },
  ],
  webServer: {
    command: `node --experimental-strip-types tests/e2e/server.ts`,
    url: `http://localhost:${PORT}/fixtures/index.html`,
    env: { PORT: String(PORT) },
    reuseExistingServer: !process.env.CI,
    stdout: 'ignore',
  },
});
