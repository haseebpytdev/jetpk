import { test, expect } from "@playwright/test";
import path from "node:path";

const authDir = path.join(process.cwd(), "tmp", "e2e-auth");

test.use({ storageState: path.join(authDir, "admin.json") });

test("admin visible navigation crawl is 100% Next shell", async ({ page }) => {
  test.setTimeout(360_000);

  await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
  await expect(page).toHaveURL(/\/admin\/dashboard/);

  const navItems = await page.evaluate(async () => {
    for (const p of [
      "/laravel/api/dashboard/session?portal=admin",
      "/api/dashboard/session?portal=admin",
    ]) {
      try {
        const res = await fetch(p, {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        if (!res.ok) continue;
        const json = (await res.json()) as {
          data?: { navigation?: Array<{ href?: string; target?: string; label?: string }> };
          navigation?: Array<{ href?: string; target?: string; label?: string }>;
        };
        return json.data?.navigation ?? json.navigation ?? null;
      } catch {
        // try next
      }
    }
    return null;
  });

  const hrefs: string[] = [];
  if (Array.isArray(navItems) && navItems.length > 0) {
    for (const item of navItems) {
      if (item?.target === "laravel") continue;
      const href = String(item?.href ?? "");
      if (!href || href.startsWith("http")) continue;
      const absolute = href.startsWith("/admin/")
        ? href
        : href.startsWith("/")
          ? `/admin/dashboard${href === "/" ? "" : href}`
          : `/admin/dashboard/${href}`;
      hrefs.push(absolute);
    }
  } else {
    const links = page.locator('aside a[href*="/admin/dashboard"]');
    const count = await links.count();
    for (let i = 0; i < count; i += 1) {
      hrefs.push((await links.nth(i).getAttribute("href")) ?? "");
    }
  }

  const unique = [...new Set(hrefs.filter(Boolean))];
  expect(unique.length).toBeGreaterThan(0);

  const results: Array<{
    url: string;
    status: number;
    heading: string;
    bladeTransition: boolean;
  }> = [];

  for (const href of unique) {
    let status = 0;
    let heading = "";
    let bladeTransition = false;
    let finalUrl = href;
    for (let attempt = 0; attempt < 3; attempt += 1) {
      const response = await page.goto(href, { waitUntil: "domcontentloaded", timeout: 45_000 });
      status = response?.status() ?? 0;
      finalUrl = page.url();
      if (status >= 500 && attempt < 2) {
        await page.waitForTimeout(200 * (attempt + 1));
        continue;
      }
      heading = ((await page.locator("h1").first().textContent().catch(() => "")) ?? "").trim();
      const html = await page.content();
      bladeTransition =
        html.includes("ota-dashboard-breadcrumbs") &&
        !html.includes("__NEXT_DATA__") &&
        !html.includes("/_next/");
      break;
    }
    results.push({ url: finalUrl, status, heading, bladeTransition });
  }

  const unexpected404 = results.filter((r) => r.status === 404).length;
  const unexpected500 = results.filter((r) => r.status >= 500).length;
  const blade = results.filter((r) => r.bladeTransition).length;

  expect(unexpected404, JSON.stringify(results, null, 2)).toBe(0);
  expect(unexpected500, JSON.stringify(results, null, 2)).toBe(0);
  expect(blade, JSON.stringify(results, null, 2)).toBe(0);
  expect(results.length).toBe(unique.length);

  // Sanitized proxy metrics
  const metrics = await page.request.get("/__e2e/metrics");
  const body = (await metrics.json()) as {
    AUTH_GATE_502_COUNT?: number;
    SQLITE_LOCK_ERRORS?: number;
    LARAVEL_WORKER_COUNT?: number;
  };
  console.log(`LARAVEL_E2E_WORKERS=${body.LARAVEL_WORKER_COUNT ?? "?"}`);
  console.log(`AUTH_GATE_502_COUNT=${body.AUTH_GATE_502_COUNT ?? "?"}`);
  console.log(`SQLITE_LOCK_ERRORS=${body.SQLITE_LOCK_ERRORS ?? "?"}`);
  expect(body.AUTH_GATE_502_COUNT ?? 1).toBe(0);
  expect(body.SQLITE_LOCK_ERRORS ?? 1).toBe(0);

  console.log(`ADMIN_VISIBLE_MENU_ITEMS_TESTED=${results.length}/${unique.length}`);
  console.log("ADMIN_AUTH_E2E=PASS");
  console.log("ADMIN_BLADE_TRANSITIONS=0");
  console.log("ADMIN_MENU_502=0");
  console.log("ADMIN_MENU_404=0");
  console.log("ADMIN_MENU_500=0");
});
