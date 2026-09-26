import { defineConfig, devices } from "@playwright/test";

/**
 * Console journeys with a deterministic, fully mocked API (page.route) — no
 * backend or database required. Full-stack end-to-end against a live API
 * belongs to the staging phase. Runs on 3101 so it never collides with the
 * student app's e2e server on 3100.
 */
export default defineConfig({
  testDir: "./e2e",
  fullyParallel: true,
  retries: 0,
  reporter: [["list"]],
  use: {
    baseURL: "http://localhost:3101",
    trace: "retain-on-failure",
  },
  projects: [{ name: "chromium", use: { ...devices["Desktop Chrome"] } }],
  webServer: {
    command: "pnpm exec next dev -p 3101",
    url: "http://localhost:3101",
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
