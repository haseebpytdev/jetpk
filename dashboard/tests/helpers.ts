import { expect, type Locator, type Page } from "@playwright/test";

/** Wait until a data table has at least one body row (post-navigation). */
export async function expectTableReady(table: Locator): Promise<void> {
  await expect(table).toBeVisible();
  await expect(table.locator("tbody tr").first()).toBeVisible();
}

/** Wait until the filter panel and Apply control are interactive. */
export async function expectFiltersReady(page: Page): Promise<void> {
  const filters = page.getByTestId("payments-filters").or(page.getByTestId("bookings-filters"));
  await expect(filters).toBeVisible();
  const apply = page.getByRole("button", { name: "Apply filters" });
  await expect(apply).toBeEnabled();
  await expect(apply).not.toHaveAttribute("aria-busy", "true");
}

/** Fill a controlled search input and verify React draft sync. */
export async function fillSearchInput(search: Locator, value: string): Promise<void> {
  await expect(search).toBeEnabled();
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await search.click();
    await search.fill(value);
    try {
      await expect(search).toHaveValue(value, { timeout: 3_000 });
      return;
    } catch {
      if (attempt === 2) {
        throw new Error(`Search input did not retain "${value}" after 3 attempts`);
      }
    }
  }
}

/** Select a filter value and verify the controlled field retained it (React draft sync). */
export async function selectFilterOption(select: Locator, value: string): Promise<void> {
  await expect(select).toBeEnabled();
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await select.selectOption(value);
    try {
      await expect(select).toHaveValue(value, { timeout: 3_000 });
      return;
    } catch {
      if (attempt === 2) {
        throw new Error(`Filter select did not retain value "${value}" after 3 attempts`);
      }
    }
  }
}

async function waitForClientUrl(page: Page, urlPattern: RegExp): Promise<void> {
  await page.waitForURL(urlPattern, { timeout: 15_000 });
}

async function clickApplyAndWaitForUrl(page: Page, apply: Locator, urlPattern: RegExp): Promise<void> {
  await expect(apply).toBeEnabled();
  await expect(apply).not.toHaveAttribute("aria-busy", "true");
  try {
    await Promise.all([waitForClientUrl(page, urlPattern), apply.click()]);
  } catch {
    await expect(apply).toBeEnabled();
    await Promise.all([waitForClientUrl(page, urlPattern), apply.click()]);
  }
}

/** Apply search via Enter (matches filter UX) and wait for URL + rendered row. */
export async function applySearchAndWaitForRow(
  page: Page,
  search: Locator,
  table: Locator,
  urlPattern: RegExp,
  visibleRowText: string,
): Promise<void> {
  await expectFiltersReady(page);
  await Promise.all([waitForClientUrl(page, urlPattern), search.press("Enter")]);
  await expectTableReady(table);
  await expect(table.getByText(visibleRowText)).toBeVisible();
}

/** Apply filters and wait for URL + rendered row before assertions. */
export async function applyFiltersAndWaitForRow(
  page: Page,
  table: Locator,
  urlPattern: RegExp,
  visibleRowText: string,
): Promise<void> {
  await expectFiltersReady(page);
  const apply = page.getByRole("button", { name: "Apply filters" });
  await clickApplyAndWaitForUrl(page, apply, urlPattern);
  await expectTableReady(table);
  await expect(table.getByText(visibleRowText)).toBeVisible();
}

/** Select a filter, re-verify draft, then apply and wait for results. */
export async function selectAndApplyFilter(
  page: Page,
  table: Locator,
  select: Locator,
  value: string,
  urlPattern: RegExp,
  visibleRowText: string,
): Promise<void> {
  await expectFiltersReady(page);
  await selectFilterOption(select, value);
  await expect(select).toHaveValue(value);
  await applyFiltersAndWaitForRow(page, table, urlPattern, visibleRowText);
}

/** Close an open drawer with Escape; targets the dialog so the key event is handled reliably. */
export async function closeDrawerWithEscape(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  await dialog.click({ position: { x: 16, y: 16 } });
  await Promise.all([
    page.waitForURL((url) => !urlMustNotMatch.test(url.href), { timeout: 15_000 }),
    page.keyboard.press("Escape"),
  ]);
  await expect(dialog).toBeHidden();
}

/** Close drawer via its labelled close control; URL clears before dialog unmounts. */
export async function closeDrawerWithButton(
  page: Page,
  closeLabel: string,
  urlMustNotMatch: RegExp,
): Promise<void> {
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  await Promise.all([
    page.waitForURL((url) => !urlMustNotMatch.test(url.href), { timeout: 15_000 }),
    page.getByRole("button", { name: closeLabel }).click(),
  ]);
  await expect(dialog).toBeHidden();
}

/** Wait for client-side pagination or page-size URL updates. */
export async function waitForUrlChange(page: Page, urlPattern: RegExp): Promise<void> {
  await waitForClientUrl(page, urlPattern);
}
