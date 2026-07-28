import { test, expect, type Page } from "@playwright/test";

function localTomorrowIso(): string {
  const date = new Date();
  date.setDate(date.getDate() + 1);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

async function interceptGroupHandoff(page: Page): Promise<{ getUrl: () => string }> {
  let capturedUrl = "";
  await page.route("**/groups/search**", async (route) => {
    capturedUrl = route.request().url();
    await route.fulfill({
      status: 200,
      contentType: "text/html",
      body: "<html><body>group-search-handoff-stub</body></html>",
    });
  });

  return {
    getUrl: () => capturedUrl,
  };
}

async function openGroupSearchTab(page: Page): Promise<void> {
  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("tab", { name: "Group Ticketing" }).click();
}

async function selectGroupSector(page: Page, pattern: RegExp): Promise<void> {
  await page.getByLabel("Sector").evaluate((select, source) => {
    const matcher = new RegExp(source.pattern, source.flags);
    const option = Array.from((select as HTMLSelectElement).options).find((item) => matcher.test(item.label));
    if (!option) {
      throw new Error(`No group sector option matched /${source.pattern}/${source.flags}`);
    }
    (select as HTMLSelectElement).value = option.value;
    select.dispatchEvent(new Event("change", { bubbles: true }));
  }, { pattern: pattern.source, flags: pattern.flags });
}

async function fillGroupSearchForm(
  page: Page,
  options: { sectorPattern: RegExp; travelDate: string; category?: "All" | "KSA" | "UAE" | "Muscat" },
): Promise<void> {
  await selectGroupSector(page, options.sectorPattern);
  const travelDateField = page.getByLabel("Travel date");
  await travelDateField.fill(options.travelDate);
  await expect(travelDateField).toHaveValue(options.travelDate);
  await expect(page.locator('[aria-label="Group ticketing search"] input[type="date"] + p')).not.toBeEmpty();
  if (options.category) {
    await page.getByRole("radio", { name: options.category }).check();
  }
}

async function submitGroupSearch(page: Page, handoff: { getUrl: () => string }): Promise<string> {
  const form = page.locator('form[aria-label="Group ticketing search"]');
  await expect(form.getByRole("button", { name: "Search Group Fares" })).toBeEnabled();
  await form.evaluate((element: HTMLFormElement) => {
    element.requestSubmit();
  });

  await expect.poll(() => handoff.getUrl() || null).not.toBeNull();
  const handoffUrl = handoff.getUrl();
  expect(handoffUrl).toBeTruthy();
  return handoffUrl;
}

test("group ticketing form exposes only Laravel search fields", async ({ page }) => {
  await openGroupSearchTab(page);

  await expect(page.getByLabel("Sector")).toBeVisible();
  await expect(page.getByLabel("Travel date")).toBeVisible();
  await expect(page.getByLabel("Group category")).toBeVisible();
  await expect(page.getByLabel("Origin")).toHaveCount(0);
  await expect(page.getByLabel("From")).toHaveCount(0);
  await expect(page.getByRole("button", { name: "Travelers and cabin" })).toHaveCount(0);
});

test("group ticketing handoff includes every actionable visible field", async ({ page }) => {
  const handoff = await interceptGroupHandoff(page);
  await openGroupSearchTab(page);

  const travelDate = localTomorrowIso();
  await fillGroupSearchForm(page, { sectorPattern: /UAE.*Dubai/, travelDate, category: "UAE" });

  const handoffUrl = await submitGroupSearch(page, handoff);
  expect(handoffUrl).toContain("/groups/search");
  expect(handoffUrl).toMatch(/sector=.*Dubai/);
  expect(handoffUrl).toContain(`date_from=${travelDate}`);
  expect(handoffUrl).toContain("category=uae");
});

test("group ticketing category all omits category query parameter", async ({ page }) => {
  const handoff = await interceptGroupHandoff(page);
  await openGroupSearchTab(page);

  const travelDate = localTomorrowIso();
  await fillGroupSearchForm(page, { sectorPattern: /KSA.*Jeddah/, travelDate, category: "All" });

  const handoffUrl = await submitGroupSearch(page, handoff);
  expect(handoffUrl).toContain("sector=");
  expect(handoffUrl).toContain(`date_from=${travelDate}`);
  expect(handoffUrl).not.toContain("category=");
});
