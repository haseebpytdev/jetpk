import { expect, type Locator, type Page } from "@playwright/test";

/** Wait until a data table has at least one body row (post-navigation). */
export async function expectTableReady(table: Locator): Promise<void> {
  await expect(table).toBeVisible();
  await expect(table.locator("tbody tr").first()).toBeVisible();
}

/** Wait until the filter panel and Apply control are interactive. */
export async function expectFiltersReady(page: Page): Promise<void> {
  const filters = page
    .getByTestId("payments-filters")
    .or(page.getByTestId("bookings-filters"))
    .or(page.getByTestId("customers-filters"))
    .or(page.getByTestId("suppliers-filters"))
    .or(page.getByTestId("agents-filters"))
    .or(page.getByTestId("pnrs-filters"))
    .or(page.getByTestId("tickets-filters"))
    .or(page.getByTestId("reports-filters"))
    .or(page.getByTestId("cms-filters"));
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

const CLIENT_URL_WAIT = { timeout: 30_000 } as const;

async function waitForClientUrl(page: Page, urlPattern: RegExp): Promise<void> {
  await expect(page).toHaveURL(urlPattern, CLIENT_URL_WAIT);
}

async function clickApplyAndWaitForUrl(page: Page, apply: Locator, urlPattern: RegExp): Promise<void> {
  await expect(apply).toBeEnabled();
  await expect(apply).not.toHaveAttribute("aria-busy", "true");
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await apply.click();
    try {
      await expect(page).toHaveURL(urlPattern, { timeout: 30_000 });
      return;
    } catch (error) {
      if (attempt === 2) {
        throw error;
      }
      await expect(apply).not.toHaveAttribute("aria-busy", "true", { timeout: 30_000 });
      await expect(apply).toBeEnabled();
    }
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
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await selectFilterOption(select, value);
    try {
      await expect(select).toHaveValue(value, { timeout: 2_000 });
      await applyFiltersAndWaitForRow(page, table, urlPattern, visibleRowText);
      return;
    } catch (error) {
      if (attempt === 2) {
        throw error;
      }
    }
  }
}

/** Close an open drawer with Escape; targets the dialog so the key event is handled reliably. */
export async function closeDrawerWithEscape(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  await dialog.click({ position: { x: 16, y: 16 } });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await page.keyboard.press("Escape");
    try {
      await expect.poll(() => urlMustNotMatch.test(page.url()), { timeout: 15_000 }).toBe(false);
      await expect(dialog).toBeHidden({ timeout: 5_000 });
      return;
    } catch (error) {
      if (attempt === 2) {
        throw error;
      }
      if (!(await dialog.isVisible())) {
        return;
      }
      await dialog.click({ position: { x: 16, y: 16 } });
    }
  }
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
    page.waitForURL((url) => !urlMustNotMatch.test(url.href), { timeout: 30_000, waitUntil: "commit" }),
    page.getByRole("button", { name: closeLabel }).click(),
  ]);
  await expect(dialog).toBeHidden();
}

/** Wait for client-side pagination or page-size URL updates. */
export async function waitForUrlChange(page: Page, urlPattern: RegExp): Promise<void> {
  await waitForClientUrl(page, urlPattern);
}

/** Wait until a reports route transition finishes and workspace content is interactive. */
export async function expectReportsReady(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: "Reports", level: 1 })).toBeVisible({ timeout: 60_000 });

  const routeLoading = page.getByLabel("Loading report route");
  if ((await routeLoading.count()) > 0) {
    await expect(routeLoading).toBeHidden({ timeout: 15_000 });
  }

  const dataLoading = page.getByLabel("Loading report data");
  if ((await dataLoading.count()) > 0) {
    await expect(dataLoading).toBeHidden({ timeout: 15_000 });
  }

  await expectFiltersReady(page);

  const workspace = page
    .getByTestId("reports-metric-grid")
    .or(page.getByText(/No report data/i))
    .or(page.getByText(/Unable to load reports/i))
    .or(page.locator("#report-date-error"));
  await expect(workspace).toBeVisible({ timeout: 15_000 });
}

/** Apply a report date preset and wait for the URL to reflect it. */
export async function applyReportsPresetAndWait(page: Page, preset: string, urlPattern: RegExp): Promise<void> {
  await expectReportsReady(page);
  const select = page.locator("#report-date-preset");
  await selectFilterOption(select, preset);
  const apply = page.getByRole("button", { name: "Apply filters" });
  await clickApplyAndWaitForUrl(page, apply, urlPattern);
  await expectReportsReady(page);
}

/** Navigate within the reports section nav and wait for the destination route. */
export async function navigateReportsSection(page: Page, linkName: string, urlPattern: RegExp): Promise<void> {
  await expectReportsReady(page);
  const nav = page.getByRole("navigation", { name: "Reports sections" });
  const link = nav.getByRole("link", { name: linkName });
  await Promise.all([page.waitForURL(urlPattern, CLIENT_URL_WAIT), link.click()]);
  await expectReportsReady(page);
}

/** Click a reports table sort header and wait for URL plus aria-sort state. */
export async function clickReportsSortHeader(
  page: Page,
  columnLabel: string,
  sortKey: string,
  direction: "asc" | "desc",
): Promise<void> {
  await expectReportsReady(page);
  const table = page.getByTestId("reports-table");
  await expect(table).toBeVisible();
  const header = table.getByRole("button", { name: columnLabel });
  await expect(header).toBeEnabled();
  await Promise.all([
    page.waitForURL(new RegExp(`sort=${sortKey}`), CLIENT_URL_WAIT),
    header.click(),
  ]);
  await expect(header).toHaveAttribute("aria-sort", direction === "asc" ? "ascending" : "descending");
}

/** Assert a chart segment label is visible within a scoped report chart card. */
export async function expectReportChartSegment(page: Page, chartTestId: string, label: string): Promise<void> {
  await expectReportsReady(page);
  const chart = page.getByTestId(chartTestId);
  await expect(chart).toBeVisible();
  const chartKey = chartTestId.replace(/^report-chart-/, "");
  const segments = chart.getByTestId(`report-chart-${chartKey}-segments`);
  await expect(segments.getByRole("listitem").filter({ hasText: new RegExp(`^${label}`) })).toBeVisible();
}

/** Open the reports export drawer and wait for the download control. */
export async function openReportsExportDrawer(page: Page): Promise<void> {
  await expectReportsReady(page);
  const exportButton = page.getByTestId("reports-export-button");
  await expect(exportButton).toBeEnabled();
  await exportButton.click();
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible({ timeout: 10_000 });
  await expect(dialog.getByRole("button", { name: "Download CSV" })).toBeVisible();
}

/** Close the export drawer with Escape (no URL change). */
export async function closeExportDrawerWithEscape(page: Page): Promise<void> {
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible();
  await dialog.click({ position: { x: 16, y: 16 } });
  await page.keyboard.press("Escape");
  await expect(dialog).toBeHidden({ timeout: 10_000 });
}

/** Wait until a CMS route transition finishes and workspace content is interactive. */
export async function expectCmsReady(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: "CMS", level: 1 })).toBeVisible({ timeout: 60_000 });

  const routeLoading = page
    .getByTestId("cms-loading-state")
    .or(page.locator('[aria-busy="true"][aria-label="Loading CMS"]'));
  if ((await routeLoading.count()) > 0) {
    await expect(routeLoading.first()).toBeHidden({ timeout: 30_000 });
  }

  await expect(
    page.getByTestId("cms-workspace").or(page.getByText(/Unable to load CMS/i)),
  ).toBeVisible({ timeout: 30_000 });
}

/** Navigate within the CMS section nav and wait for the destination route. */
export async function navigateCmsSection(page: Page, linkName: string, urlPattern: RegExp): Promise<void> {
  await expectCmsReady(page);
  const nav = page.getByRole("navigation", { name: "CMS sections" });
  const link = nav.getByRole("link", { name: linkName, exact: true });
  await link.click();
  await expect(page).toHaveURL(urlPattern, { timeout: 30_000 });
  await expectCmsReady(page);
}

const CMS_URL_WAIT = { timeout: 30_000, waitUntil: "commit" as const };

/** Apply CMS filters and wait for URL update. */
export async function applyCmsFiltersAndWait(page: Page, urlPattern: RegExp): Promise<void> {
  await expectCmsReady(page);
  const apply = page.getByRole("button", { name: "Apply filters" });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(apply).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), apply.click()]);
      await expectCmsReady(page);
      return;
    } catch (error) {
      if (attempt === 2) {
        throw error;
      }
      await expectCmsReady(page);
    }
  }
}

/** Click a CMS table sort header and wait for URL update. */
export async function clickCmsSortHeader(page: Page, columnLabel: string, sortKey: string): Promise<void> {
  await expectCmsReady(page);
  const table = page.getByTestId("cms-table");
  await expectTableReady(table);
  const header = table.getByRole("button", { name: columnLabel });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(header).toBeEnabled();
    try {
      await Promise.all([
        page.waitForURL(new RegExp(`sort=${sortKey}`), CMS_URL_WAIT),
        header.click(),
      ]);
      return;
    } catch (error) {
      if (attempt === 2) {
        throw error;
      }
      await expectCmsReady(page);
    }
  }
}

/** Reset CMS filters and wait for cleared URL state. */
export async function resetCmsFiltersAndWait(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  await expectCmsReady(page);
  await page.getByRole("button", { name: "Reset filters" }).click();
  await expect.poll(() => page.url(), { timeout: 30_000 }).not.toMatch(urlMustNotMatch);
  await expectCmsReady(page);
}

/** Open a CMS record drawer from the data table. */
export async function openCmsRecordDrawer(page: Page, recordId: string): Promise<void> {
  await expectCmsReady(page);
  const table = page.getByTestId("cms-table");
  await expectTableReady(table);
  const rowButton = table.getByRole("button", { name: recordId });
  await expect(rowButton).toBeVisible();
  await rowButton.click();
  await expect(page).toHaveURL(new RegExp(`selected=${recordId.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`), {
    timeout: 30_000,
  });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
}

/** Change CMS preview mode inside an open drawer. */
export async function changeCmsPreviewMode(page: Page, modeLabel: string, urlPattern?: RegExp): Promise<void> {
  const selector = page.getByTestId("cms-preview-mode-selector");
  await expect(selector).toBeVisible();
  const button = selector.getByRole("button", { name: modeLabel });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await button.click();
    if (urlPattern) {
      try {
        await expect.poll(() => page.url(), { timeout: 15_000 }).toMatch(urlPattern);
        break;
      } catch (error) {
        if (attempt === 2) {
          throw error;
        }
      }
    } else {
      break;
    }
  }
  await expect(page.getByTestId("cms-preview-frame")).toBeVisible();
  if (urlPattern) {
    await expect(button).toHaveAttribute("aria-pressed", "true");
  }
}

/** Apply local preview form changes in section drawer. */
export async function applyCmsLocalPreview(page: Page, heading: string): Promise<void> {
  const form = page.getByTestId("cms-local-preview-form");
  await expect(form).toBeVisible();
  const headingInput = form.locator("#cms-preview-heading");
  await fillSearchInput(headingInput, heading);
  await form.getByRole("button", { name: "Apply to preview" }).click();
  await expect(page.getByText("Unsaved preview")).toBeVisible();
}

/** Reset local preview form in section drawer. */
export async function resetCmsLocalPreview(page: Page): Promise<void> {
  const form = page.getByTestId("cms-local-preview-form");
  await form.getByRole("button", { name: "Reset preview" }).click();
}

/** Reset reports filters and wait for cleared URL state. */
export async function resetReportsFiltersAndWait(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  await expectReportsReady(page);
  await Promise.all([
    page.waitForURL((url) => !urlMustNotMatch.test(url.href), { timeout: 30_000, waitUntil: "commit" }),
    page.getByRole("button", { name: "Reset filters" }).click(),
  ]);
  await expectReportsReady(page);
}
