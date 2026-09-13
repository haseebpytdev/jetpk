import type { Page } from "@playwright/test";

const BASE = process.env.JP_CANARY_UAT_BASE ?? "https://jetpakistan.pk";

export async function loginCanaryAdmin(page: Page, email: string, password: string): Promise<void> {
  await page.goto(`${BASE}/login`, { waitUntil: "domcontentloaded", timeout: 120000 });
  await page.waitForSelector('[name="login"]', { state: "visible", timeout: 60000 });

  await page.request.get(`${BASE}/laravel/api/public/content/csrf-token`, {
    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
  });

  const cookies = await page.context().cookies();
  const xsrf = cookies.find((c) => c.name === "XSRF-TOKEN");
  const token = xsrf ? decodeURIComponent(xsrf.value) : "";

  async function postLogin(csrf: string) {
    return page.request.post(`${BASE}/laravel/login`, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": csrf,
      },
      form: { login: email, password, remember: "1", client_slug: "jetpk" },
    });
  }

  let response = await postLogin(token);
  if (response.status() === 419) {
    await page.request.get(`${BASE}/laravel/api/public/content/csrf-token`, {
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
    });
    const retryCookies = await page.context().cookies();
    const retryXsrf = retryCookies.find((c) => c.name === "XSRF-TOKEN");
    response = await postLogin(retryXsrf ? decodeURIComponent(retryXsrf.value) : token);
  }

  const data = await response.json();
  if (!response.ok() || data.ok !== true) {
    throw new Error(`login_failed:${response.status()}`);
  }

  const dest = typeof data.redirect === "string" && data.redirect ? data.redirect : "/admin/dashboard";
  await page.goto(`${BASE}${dest}`, { waitUntil: "domcontentloaded", timeout: 120000 });
}
