import { defineConfig, devices } from "@playwright/test";
import { API_PORT, LARAVEL_ENV, ROOT, WEB_PORT } from "./env";

/**
 * Full stack E2E: Laravel (PHP built-in server, SQLite) + built widget.
 * Run from the widget package: npm --prefix widget run e2e:full
 */
export default defineConfig({
  testDir: ".",
  testMatch: /.*\.e2e\.ts/,
  globalSetup: "./global-setup.ts",
  timeout: 45_000,
  expect: { timeout: 10_000 },
  workers: 1,
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  reporter: process.env.CI
    ? [
        ["github"],
        ["html", { open: "never", outputFolder: "playwright-report" }],
      ]
    : [["list"]],
  outputDir: "test-results",
  use: { trace: "retain-on-failure", screenshot: "only-on-failure" },
  projects: [
    { name: "chromium", use: { ...devices["Desktop Chrome"] } },
    { name: "firefox", use: { ...devices["Desktop Firefox"] } },
    { name: "webkit", use: { ...devices["Desktop Safari"] } },
  ],
  webServer: [
    {
      // The router uses getcwd() as document root, so it runs inside public/.
      command: `php -S 127.0.0.1:${API_PORT} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
      cwd: `${ROOT}/public`,
      env: LARAVEL_ENV as Record<string, string>,
      url: `http://127.0.0.1:${API_PORT}/up`,
      reuseExistingServer: false,
      stdout: "ignore",
      stderr: "pipe",
    },
    {
      command: "node --experimental-strip-types e2e/static-server.ts",
      cwd: ROOT,
      env: { PORT: String(WEB_PORT) },
      url: `http://127.0.0.1:${WEB_PORT}/index.html`,
      reuseExistingServer: false,
      stdout: "ignore",
    },
  ],
});
