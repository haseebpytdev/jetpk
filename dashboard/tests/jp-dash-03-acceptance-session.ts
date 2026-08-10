/**
 * Playwright acceptance session guard + keepalive for long JP-DASH-03 runs.
 * Local tooling only — no production session lifetime changes.
 */
import { test as base, expect, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const storagePath = path.resolve(
  process.env.JP_ADMIN_STORAGE_STATE ??
    path.join(process.cwd(), "..", "tmp/jp-dash-03-admin-storage-state.json"),
);

const KEEPALIVE_PATH = "/api/dashboard/overview";
const KEEPALIVE_INTERVAL_MS = Number(process.env.JP_ACCEPTANCE_KEEPALIVE_MS ?? 4 * 60 * 1000);
const SESSION_UNAVAILABLE = /Session unavailable|Dashboard unavailable|Dashboard temporarily unavailable/i;
const PREVIEW_LABEL = /preview/i;

let keepaliveTimer: ReturnType<typeof setInterval> | null = null;
let sessionStale = false;

export function getAdminStoragePath() {
  return storagePath;
}

export function adminStorageStateExists() {
  return fs.existsSync(storagePath);
}

function stopKeepalive() {
  if (keepaliveTimer) {
    clearInterval(keepaliveTimer);
    keepaliveTimer = null;
  }
}

export function startAcceptanceKeepalive(
  requestContext: { get: (url: string) => Promise<{ status: () => number; ok: () => boolean }> },
) {
  if (keepaliveTimer || sessionStale) {
    return;
  }

  keepaliveTimer = setInterval(() => {
    requestContext
      .get(KEEPALIVE_PATH)
      .then((response) => {
        if (!response.ok() || response.status() === 401 || response.status() === 403) {
          sessionStale = true;
          stopKeepalive();
          console.error("ADMIN_PLAYWRIGHT_SESSION=STALE");
        }
      })
      .catch(() => {
        sessionStale = true;
        stopKeepalive();
        console.error("ADMIN_PLAYWRIGHT_SESSION=STALE");
      });
  }, KEEPALIVE_INTERVAL_MS);

  if (typeof keepaliveTimer.unref === "function") {
    keepaliveTimer.unref();
  }
}

export function isAcceptanceSessionStale() {
  return sessionStale;
}

export async function assertAdminSessionAlive(page: Page) {
  if (!adminStorageStateExists()) {
    throw new Error("ADMIN_PLAYWRIGHT_SESSION=MISSING");
  }

  const response = await page.goto("/admin/dashboard", {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });
  if ((response?.status() ?? 0) >= 400) {
    sessionStale = true;
    throw new Error("ADMIN_PLAYWRIGHT_SESSION=STALE");
  }

  const body = await page.locator("body").innerText();
  if (SESSION_UNAVAILABLE.test(body)) {
    sessionStale = true;
    throw new Error("ADMIN_PLAYWRIGHT_SESSION=STALE");
  }

  const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
  if (!portalLabel || PREVIEW_LABEL.test(portalLabel)) {
    sessionStale = true;
    throw new Error("ADMIN_PLAYWRIGHT_SESSION=STALE");
  }
}

export const test = base.extend({
  page: async ({ page }, use) => {
    await assertAdminSessionAlive(page);
    startAcceptanceKeepalive(page.request);
    if (sessionStale) {
      throw new Error("ADMIN_PLAYWRIGHT_SESSION=STALE");
    }
    await use(page);
    stopKeepalive();
  },
});

export { expect };
