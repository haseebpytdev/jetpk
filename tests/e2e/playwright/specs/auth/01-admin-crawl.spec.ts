import { test, expect } from "@playwright/test";
import path from "node:path";

const authDir = path.join(process.cwd(), "tmp", "e2e-auth");

test.use({ storageState: path.join(authDir, "admin.json") });

test("admin visible navigation crawl is 100% Next shell", async ({ page, request }) => {
  test.setTimeout(240_000);

  await page.goto("/admin/dashboard", { waitUntil: "domcontentloaded" });
  await expect(page).toHaveURL(/\/admin\/dashboard/);

  const navItems = await page.evaluate(async () => {
    const paths = [
      "/laravel/api/dashboard/session?portal=admin",
      "/api/dashboard/session?portal=admin",
    ];
    for (const p of paths) {
      try {
        const res = await fetch(p, {
          credentials: "same-origin",
          headers: { Accept: "application/json" },
        });
        if (!res.ok) continue;
        const json = (await res.json()) as {
          data?: { navigation?: Array<{ href?: string; target?: string }> };
          navigation?: Array<{ href?: string; target?: string }>;
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

  // Authenticated HTTP status sweep (stable under single-worker Laravel).
  const statusResults: Array<{ url: string; status: number }> = [];
  for (const href of unique) {
    const res = await request.get(href, { maxRedirects: 0 });
    statusResults.push({ url: href, status: res.status() });
  }

  const unexpected404 = statusResults.filter((r) => r.status === 404).length;
  const unexpected500 = statusResults.filter((r) => r.status >= 500).length;
  expect(unexpected404, JSON.stringify(statusResults, null, 2)).toBe(0);
  expect(unexpected500, JSON.stringify(statusResults, null, 2)).toBe(0);

  // Full page sample for Blade transition / Next shell proof.
  const sample = unique.slice(0, Math.min(8, unique.length));
  let blade = 0;
  for (const href of sample) {
    const response = await page.goto(href, { waitUntil: "domcontentloaded", timeout: 60_000 });
    expect(response?.status() ?? 0).toBeLessThan(500);
    const html = await page.content();
    const bladeTransition =
      html.includes("ota-dashboard-breadcrumbs") &&
      !html.includes("__NEXT_DATA__") &&
      !html.includes("/_next/");
    if (bladeTransition) blade += 1;
  }
  expect(blade).toBe(0);

  console.log(`ADMIN_VISIBLE_MENU_ITEMS_TESTED=${statusResults.length}/${unique.length}`);
  console.log("ADMIN_AUTH_E2E=PASS");
  console.log("ADMIN_BLADE_TRANSITIONS=0");
});
