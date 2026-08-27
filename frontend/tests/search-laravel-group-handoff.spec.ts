import { test, expect, type Page } from "@playwright/test";

const mockHandoffFacets = {
  airlines: [{ value: "Emirates", label: "Emirates" }],
  sectors: [
    { value: "DXB", label: "UAE — Dubai" },
    { value: "JED", label: "KSA — Jeddah" },
  ],
  categories: [
    { value: "ksa", label: "KSA", inventory_count: 2 },
    { value: "uae", label: "UAE", inventory_count: 3 },
  ],
  date_bounds: { minimum: "2026-01-01", maximum: "2027-12-31" },
  travel_date_match: { mode: "EXACT_THEN_NEARBY", tolerance_days: 3 },
};

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
  await page.route("**/laravel/groups/search/facets**", async (route) => {
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(mockHandoffFacets),
    });
  });
  await page.goto("/", { waitUntil: "load" });
  await page.getByRole("tab", { name: "Groups" }).click();
  await expect(page.getByTestId("group-sector-select")).toBeEnabled();
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
  options: { sectorPattern: RegExp; travelDate: string; category?: "ksa" | "uae" },
): Promise<void> {
  await selectGroupSector(page, options.sectorPattern);
  const travelDateField = page.getByLabel("Travel date");
  await travelDateField.fill(options.travelDate);
  await expect(travelDateField).toHaveValue(options.travelDate);
  await expect(page.locator('[aria-label="Groups search"] input[type="date"] + p')).not.toBeEmpty();
  if (options.category) {
    await page.getByTestId(`group-category-card-${options.category}`).click();
  }
}

async function submitGroupSearch(page: Page, handoff: { getUrl: () => string }): Promise<string> {
  const form = page.locator('form[aria-label="Groups search"]');
  await expect(form.getByRole("button", { name: "Search Groups" })).toBeEnabled();
  await form.evaluate((element: HTMLFormElement) => {
    element.requestSubmit();
  });

  await expect.poll(() => handoff.getUrl() || null).not.toBeNull();
  const handoffUrl = handoff.getUrl();
  expect(handoffUrl).toBeTruthy();
  return handoffUrl;
}

test("Groups form exposes shared compact search fields", async ({ page }) => {
  await openGroupSearchTab(page);

  await expect(page.getByLabel("Airline")).toBeVisible();
  await expect(page.getByLabel("Sector")).toBeVisible();
  await expect(page.getByLabel("Travel date")).toBeVisible();
  await expect(page.getByTestId("group-category-cards")).toBeVisible();
  await expect(page.getByLabel("Origin")).toHaveCount(0);
  await expect(page.getByLabel("From")).toHaveCount(0);
  await expect(page.getByRole("button", { name: "Travelers and cabin" })).toHaveCount(0);
});

test("Groups handoff includes every actionable visible field", async ({ page }) => {
  const handoff = await interceptGroupHandoff(page);
  await openGroupSearchTab(page);

  const travelDate = localTomorrowIso();
  await fillGroupSearchForm(page, { sectorPattern: /UAE.*Dubai/, travelDate, category: "uae" });

  const handoffUrl = await submitGroupSearch(page, handoff);
  expect(handoffUrl).toContain("/groups/search");
  expect(handoffUrl).toContain("sector=DXB");
  expect(handoffUrl).toContain(`date_from=${travelDate}`);
  expect(handoffUrl).toContain("category=uae");
});

test("Groups without category omits category query parameter", async ({ page }) => {
  const handoff = await interceptGroupHandoff(page);
  await openGroupSearchTab(page);

  const travelDate = localTomorrowIso();
  await fillGroupSearchForm(page, { sectorPattern: /KSA.*Jeddah/, travelDate });

  const handoffUrl = await submitGroupSearch(page, handoff);
  expect(handoffUrl).toContain("sector=JED");
  expect(handoffUrl).toContain(`date_from=${travelDate}`);
  expect(handoffUrl).not.toContain("category=");
});
