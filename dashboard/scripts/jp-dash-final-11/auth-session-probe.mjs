import { FINAL11_AUTH_ROLES, FINAL11_ROLE_KEYS, BASE_URL } from "./final-11-auth-roles.mjs";
import { laravelPortalUrl, LARAVEL_JSON_HEADERS } from "./final-11-laravel-probe.mjs";

/**
 * @param {import('playwright').BrowserContext} context
 * @param {import('./final-11-auth-roles.mjs').Final11AuthRole} role
 */
export async function probeRoleSession(context, role) {
  try {
    if (role.portal === "admin" || role.portal === "staff") {
      const resp = await context.request.get(`${BASE_URL}/laravel/api/dashboard/session`, {
        headers: { Accept: "application/json" },
        timeout: 30_000,
      });
      return resp.ok() ? "PASS" : `FAIL_HTTP_${resp.status()}`;
    }
    if (role.portal === "agent") {
      const resp = await context.request.get(laravelPortalUrl("/agent?format=json"), {
        headers: LARAVEL_JSON_HEADERS,
        timeout: 30_000,
      });
      return resp.status() === 200 ? "PASS" : `FAIL_HTTP_${resp.status()}`;
    }
    if (role.portal === "customer") {
      const page = await context.newPage();
      const resp = await page.goto(`${BASE_URL}${role.postLoginPath}`, {
        waitUntil: "domcontentloaded",
        timeout: 60_000,
      });
      const ok = Boolean(resp && resp.status() < 500 && !page.url().includes("/login"));
      await page.close();
      return ok ? "PASS" : "FAIL_SHELL";
    }
    if (role.portal === "legacy") {
      const page = await context.newPage();
      const resp = await page.goto(`${BASE_URL}${role.postLoginPath}`, {
        waitUntil: "domcontentloaded",
        timeout: 60_000,
      });
      const body = await page.locator("body").innerText().catch(() => "");
      const ok =
        Boolean(resp && resp.status() < 500) &&
        (/legacy account|no longer supported/i.test(body) || page.url().includes("/account/legacy"));
      await page.close();
      return ok ? "PASS" : "FAIL_LEGACY";
    }
    return "FAIL_UNKNOWN_PORTAL";
  } catch {
    return "FAIL_NETWORK";
  }
}

/**
 * @param {import('playwright').Browser} browser
 * @param {Record<string, string>} storagePaths roleKey -> absolute path
 */
export async function probeAllRoleSessions(browser, storagePaths) {
  /** @type {Record<string, string>} */
  const results = {};
  let pass = 0;
  let fail = 0;
  for (const roleKey of FINAL11_ROLE_KEYS) {
    const role = FINAL11_AUTH_ROLES[roleKey];
    const storagePath = storagePaths[roleKey];
    if (!storagePath) {
      results[roleKey] = "MISSING_STORAGE";
      fail += 1;
      continue;
    }
    const context = await browser.newContext({ storageState: storagePath });
    const status = await probeRoleSession(context, role);
    results[roleKey] = status;
    await context.close();
    if (status === "PASS") {
      pass += 1;
    } else {
      fail += 1;
    }
  }
  return { results, pass, fail, expected: FINAL11_ROLE_KEYS.length };
}
