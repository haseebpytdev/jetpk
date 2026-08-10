/**
 * Playwright acceptance session guard + remember recovery for JP-DASH-03 runs.
 * Local tooling only — no production session lifetime changes.
 */
import { test as base, expect, chromium, type Page } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";

const adminStoragePath = path.resolve(
  process.env.JP_ADMIN_STORAGE_STATE ??
    path.join(process.cwd(), "..", "tmp/jp-dash-03-admin-storage-state.json"),
);

const staffStoragePath = path.resolve(
  process.env.JP_STAFF_STORAGE_STATE ??
    path.join(process.cwd(), "..", "tmp/jp-dash-03-staff-storage-state.json"),
);

const KEEPALIVE_PATH = "/api/dashboard/overview";
const KEEPALIVE_INTERVAL_MS = Number(process.env.JP_ACCEPTANCE_KEEPALIVE_MS ?? 4 * 60 * 1000);
const SESSION_UNAVAILABLE = /Session unavailable|Dashboard unavailable|Dashboard temporarily unavailable/i;
const PREVIEW_LABEL = /preview/i;

type SessionRole = "admin" | "staff";

type RoleConfig = {
  storagePath: string;
  dashboardPath: string;
  portalLabelPattern: RegExp;
};

const ROLE_CONFIG: Record<SessionRole, RoleConfig> = {
  admin: {
    storagePath: adminStoragePath,
    dashboardPath: "/admin/dashboard",
    portalLabelPattern: /admin/i,
  },
  staff: {
    storagePath: staffStoragePath,
    dashboardPath: "/staff/dashboard",
    portalLabelPattern: /staff/i,
  },
};

let keepaliveTimer: ReturnType<typeof setInterval> | null = null;
let sessionStale = false;

export function getAdminStoragePath() {
  return adminStoragePath;
}

export function getStaffStoragePath() {
  return staffStoragePath;
}

export function adminStorageStateExists() {
  return fs.existsSync(adminStoragePath);
}

export function staffStorageStateExists() {
  return fs.existsSync(staffStoragePath);
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

async function attemptRememberRecovery(role: SessionRole): Promise<boolean> {
  const config = ROLE_CONFIG[role];
  if (!fs.existsSync(config.storagePath)) {
    return false;
  }

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ storageState: config.storagePath });
  const page = await context.newPage();

  try {
    const response = await page.goto(config.dashboardPath, {
      waitUntil: "domcontentloaded",
      timeout: 120_000,
    });
    if ((response?.status() ?? 0) >= 400) {
      return false;
    }

    const body = await page.locator("body").innerText();
    if (SESSION_UNAVAILABLE.test(body)) {
      return false;
    }

    const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
    if (!portalLabel || PREVIEW_LABEL.test(portalLabel)) {
      return false;
    }

    if (!config.portalLabelPattern.test(portalLabel)) {
      return false;
    }

    await context.storageState({ path: config.storagePath });
    return true;
  } catch {
    return false;
  } finally {
    await browser.close();
  }
}

async function verifyDashboardSession(page: Page, role: SessionRole) {
  const config = ROLE_CONFIG[role];
  const response = await page.goto(config.dashboardPath, {
    waitUntil: "domcontentloaded",
    timeout: 120_000,
  });

  if ((response?.status() ?? 0) >= 400) {
    return false;
  }

  const body = await page.locator("body").innerText();
  if (SESSION_UNAVAILABLE.test(body)) {
    return false;
  }

  const portalLabel = await page.getByTestId("dashboard-portal-label").textContent();
  if (!portalLabel || PREVIEW_LABEL.test(portalLabel)) {
    return false;
  }

  return config.portalLabelPattern.test(portalLabel);
}

export async function assertAdminSessionAlive(page: Page) {
  if (!adminStorageStateExists()) {
    throw new Error("ADMIN_PLAYWRIGHT_SESSION=MISSING");
  }

  if (await verifyDashboardSession(page, "admin")) {
    return;
  }

  const recovered = await attemptRememberRecovery("admin");
  if (!recovered) {
    sessionStale = true;
    throw new Error("ADMIN_PLAYWRIGHT_SESSION=REAUTH_REQUIRED");
  }

  if (!(await verifyDashboardSession(page, "admin"))) {
    sessionStale = true;
    throw new Error("ADMIN_PLAYWRIGHT_SESSION=REAUTH_REQUIRED");
  }
}

export async function assertStaffSessionAlive(page: Page) {
  if (!staffStorageStateExists()) {
    throw new Error("STAFF_PLAYWRIGHT_SESSION=MISSING");
  }

  if (await verifyDashboardSession(page, "staff")) {
    return;
  }

  const recovered = await attemptRememberRecovery("staff");
  if (!recovered) {
    throw new Error("STAFF_PLAYWRIGHT_SESSION=REAUTH_REQUIRED");
  }

  if (!(await verifyDashboardSession(page, "staff"))) {
    throw new Error("STAFF_PLAYWRIGHT_SESSION=REAUTH_REQUIRED");
  }
}

export const test = base.extend({
  page: async ({ page }, use) => {
    await assertAdminSessionAlive(page);
    startAcceptanceKeepalive(page.request);
    if (sessionStale) {
      throw new Error("ADMIN_PLAYWRIGHT_SESSION=REAUTH_REQUIRED");
    }
    await use(page);
    stopKeepalive();
  },
});

export const staffTest = base.extend({
  page: async ({ page }, use) => {
    await assertStaffSessionAlive(page);
    await use(page);
  },
});

export { expect };
