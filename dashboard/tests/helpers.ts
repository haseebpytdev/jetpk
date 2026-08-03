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
    .or(page.getByTestId("cms-filters"))
    .or(page.getByTestId("users-filters"))
    .or(page.getByTestId("roles-filters"))
    .or(page.getByTestId("permissions-filters"))
    .or(page.getByTestId("audit-filters"));
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
  await expect(table.getByText(visibleRowText).first()).toBeVisible();
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
  await expect(table.getByText(visibleRowText).first()).toBeVisible();
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
    try {
      await Promise.all([
        page.waitForURL((url) => !urlMustNotMatch.test(url.href), { timeout: 30_000, waitUntil: "commit" }),
        page.keyboard.press("Escape"),
      ]);
      await expect(dialog).toBeHidden({ timeout: 5_000 });
      return;
    } catch (error) {
      if (attempt === 2) {
        throw error;
      }
      if (!(await dialog.isVisible())) {
        await expect.poll(() => urlMustNotMatch.test(page.url()), { timeout: 5_000 }).toBe(false);
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

/** Wait until a users route transition finishes and workspace content is interactive. */
export async function expectUsersReady(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: "Users", level: 1 })).toBeVisible({ timeout: 60_000 });

  const routeLoading = page
    .getByTestId("users-loading-state")
    .or(page.locator('[aria-busy="true"][aria-label="Loading users"]'));
  if ((await routeLoading.count()) > 0) {
    await expect(routeLoading.first()).toBeHidden({ timeout: 30_000 });
  }

  await expect(
    page.getByTestId("users-workspace").or(page.getByText(/Unable to load users/i)),
  ).toBeVisible({ timeout: 30_000 });
}

/** Apply users filters and wait for URL update. */
export async function applyUsersFiltersAndWait(page: Page, urlPattern: RegExp): Promise<void> {
  await expectUsersReady(page);
  const apply = page.getByRole("button", { name: "Apply filters" });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(apply).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), apply.click()]);
      await expectUsersReady(page);
      return;
    } catch (error) {
      if (attempt === 2) throw error;
      await expectUsersReady(page);
    }
  }
}

/** Click a users table sort header and wait for URL update. */
export async function clickUsersSortHeader(page: Page, columnLabel: string, sortKey: string): Promise<void> {
  await expectUsersReady(page);
  const table = page.getByTestId("users-table");
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
      if (attempt === 2) throw error;
      await expectUsersReady(page);
    }
  }
}

/** Reset users filters and wait for cleared URL state. */
export async function resetUsersFiltersAndWait(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  await expectUsersReady(page);
  await page.getByRole("button", { name: "Reset filters" }).click();
  await expect.poll(() => page.url(), { timeout: 30_000 }).not.toMatch(urlMustNotMatch);
  await expectUsersReady(page);
}

/** Open a user drawer from the data table. */
export async function openUserDrawer(page: Page, userId: string): Promise<void> {
  await expectUsersReady(page);
  const table = page.getByTestId("users-table");
  await expectTableReady(table);
  const rowButton = table.getByRole("button", { name: userId });
  const urlPattern = new RegExp(`selected=${userId.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`);
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(rowButton).toBeVisible();
    await expect(rowButton).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), rowButton.click()]);
      await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
      return;
    } catch (error) {
      if (attempt === 2) throw error;
      await expectUsersReady(page);
    }
  }
}

/** Apply role preview in user drawer. */
export async function applyUserRolePreview(page: Page, roleId: string): Promise<void> {
  const preview = page.getByTestId("role-assignment-preview");
  await expect(preview).toBeVisible();
  const select = preview.locator("#role-preview-select");
  await selectFilterOption(select, roleId);
  await preview.getByRole("button", { name: "Apply to preview" }).click();
}

/** Reset role preview in user drawer. */
export async function resetUserRolePreview(page: Page): Promise<void> {
  const preview = page.getByTestId("role-assignment-preview");
  await preview.getByRole("button", { name: "Reset preview" }).click();
}

/** Wait until a roles route transition finishes and workspace content is interactive. */
export async function expectRolesReady(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: "Users", level: 1 })).toBeVisible({ timeout: 60_000 });

  const routeLoading = page
    .getByTestId("roles-loading-state")
    .or(page.locator('[aria-busy="true"][aria-label="Loading roles"]'));
  if ((await routeLoading.count()) > 0) {
    await expect(routeLoading.first()).toBeHidden({ timeout: 30_000 });
  }

  await expect(
    page.getByTestId("roles-workspace").or(page.getByText(/Unable to load users/i)),
  ).toBeVisible({ timeout: 30_000 });
}

/** Apply roles filters and wait for URL update. */
export async function applyRolesFiltersAndWait(page: Page, urlPattern: RegExp): Promise<void> {
  await expectRolesReady(page);
  const apply = page.getByRole("button", { name: "Apply filters" });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(apply).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), apply.click()]);
      await expectRolesReady(page);
      return;
    } catch (error) {
      if (attempt === 2) throw error;
      await expectRolesReady(page);
    }
  }
}

/** Click a roles table sort header and wait for URL update. */
export async function clickRolesSortHeader(page: Page, columnLabel: string, sortKey: string): Promise<void> {
  await expectRolesReady(page);
  const table = page.getByTestId("roles-table");
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
      if (attempt === 2) throw error;
      await expectRolesReady(page);
    }
  }
}

/** Reset roles filters and wait for cleared URL state. */
export async function resetRolesFiltersAndWait(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  await expectRolesReady(page);
  const filters = page.getByTestId("roles-filters");
  await filters.getByRole("button", { name: "Reset filters" }).click();
  await expect.poll(() => page.url(), { timeout: 30_000 }).not.toMatch(urlMustNotMatch);
  await expectRolesReady(page);
}

/** Open a role drawer from the data table. */
export async function openRoleDrawer(page: Page, roleId: string): Promise<void> {
  await expectRolesReady(page);
  const table = page.getByTestId("roles-table");
  await expectTableReady(table);
  const rowButton = table.getByRole("button", { name: roleId });
  const urlPattern = new RegExp(`selected=${roleId.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`);
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(rowButton).toBeVisible();
    await expect(rowButton).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), rowButton.click()]);
      await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
      return;
    } catch (error) {
      if (attempt === 2) throw error;
      await expectRolesReady(page);
    }
  }
}

/** Apply permission preview in role drawer. */
export async function applyRolePermissionPreview(page: Page, permissionKey: string): Promise<void> {
  const preview = page.getByTestId("permission-assignment-preview");
  await expect(preview).toBeVisible();
  const select = preview.locator("#permission-preview-select");
  await selectFilterOption(select, permissionKey);
  await preview.getByRole("button", { name: "Apply to preview" }).click();
}

/** Reset permission preview in role drawer. */
export async function resetRolePermissionPreview(page: Page): Promise<void> {
  const preview = page.getByTestId("permission-assignment-preview");
  await preview.getByRole("button", { name: "Reset preview" }).click();
}

/** Wait until a permissions route transition finishes and workspace content is interactive. */
export async function expectPermissionsReady(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: "Users", level: 1 })).toBeVisible({ timeout: 60_000 });

  const routeLoading = page
    .getByTestId("permissions-loading-state")
    .or(page.locator('[aria-busy="true"][aria-label="Loading permissions"]'));
  if ((await routeLoading.count()) > 0) {
    await expect(routeLoading.first()).toBeHidden({ timeout: 30_000 });
  }

  await expect(
    page.getByTestId("permissions-workspace").or(page.getByText(/Unable to load users/i)),
  ).toBeVisible({ timeout: 30_000 });
}

/** Apply permissions filters and wait for URL update. */
export async function applyPermissionsFiltersAndWait(page: Page, urlPattern: RegExp): Promise<void> {
  await expectPermissionsReady(page);
  const apply = page.getByRole("button", { name: "Apply filters" });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(apply).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), apply.click()]);
      await expectPermissionsReady(page);
      return;
    } catch (error) {
      if (attempt === 2) throw error;
      await expectPermissionsReady(page);
    }
  }
}

/** Click a permissions table sort header and wait for URL update. */
export async function clickPermissionsSortHeader(page: Page, columnLabel: string, sortKey: string): Promise<void> {
  await expectPermissionsReady(page);
  const table = page.getByTestId("permissions-table");
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
      if (attempt === 2) throw error;
      await expectPermissionsReady(page);
    }
  }
}

/** Reset permissions filters and wait for cleared URL state. */
export async function resetPermissionsFiltersAndWait(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  await expectPermissionsReady(page);
  await page.getByRole("button", { name: "Reset filters" }).click();
  await expect.poll(() => page.url(), { timeout: 30_000 }).not.toMatch(urlMustNotMatch);
  await expectPermissionsReady(page);
}

/** Open a permission drawer from the data table. */
export async function openPermissionDrawer(page: Page, permissionId: string): Promise<void> {
  await expectPermissionsReady(page);
  const table = page.getByTestId("permissions-table");
  await expectTableReady(table);
  const rowButton = table.getByRole("button", { name: permissionId });
  await rowButton.click();
  await expect(page).toHaveURL(new RegExp(`selected=${permissionId.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`), {
    timeout: 30_000,
  });
  await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
}

/** Wait until a settings route transition finishes and workspace content is interactive. */
export async function expectSettingsReady(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: "Settings", level: 1 })).toBeVisible({ timeout: 60_000 });

  const routeLoading = page
    .getByTestId("settings-loading-state")
    .or(page.locator('[aria-busy="true"][aria-label="Loading settings"]'));
  if ((await routeLoading.count()) > 0) {
    await expect(routeLoading.first()).toBeHidden({ timeout: 30_000 });
  }

  await expect(
    page
      .getByTestId("settings-overview")
      .or(page.getByTestId("general-settings-workspace"))
      .or(page.getByTestId("security-settings-workspace"))
      .or(page.getByTestId("notification-settings-workspace"))
      .or(page.getByTestId("integration-settings-workspace"))
      .or(page.getByText(/Unable to load settings/i))
      .or(page.getByText(/No settings data/i)),
  ).toBeVisible({ timeout: 30_000 });
}

async function getSettingsPreviewForm(page: Page, workspaceTestId: string) {
  const workspace = page.getByTestId(workspaceTestId);
  await expect(workspace).toBeVisible();
  return workspace.getByTestId("settings-local-preview-form");
}

async function applySettingsFieldPreview(
  page: Page,
  workspaceTestId: string,
  fieldLabel: string,
  value: string,
): Promise<void> {
  await expectSettingsReady(page);
  const form = await getSettingsPreviewForm(page, workspaceTestId);
  const control = form.getByLabel(fieldLabel);
  const tagName = await control.evaluate((el) => el.tagName.toLowerCase());
  if (tagName === "select") {
    await selectFilterOption(control, value);
  } else {
    await fillSearchInput(control, value);
  }
  await form.getByRole("button", { name: "Apply to preview" }).click();
  await expect(page.getByText("Unsaved preview")).toBeVisible();
}

async function resetSettingsSectionPreview(page: Page, workspaceTestId: string): Promise<void> {
  const form = await getSettingsPreviewForm(page, workspaceTestId);
  await form.getByRole("button", { name: "Reset preview" }).click();
}

/** Apply local preview changes on general settings. */
export async function applyGeneralSettingsPreview(page: Page, fieldLabel: string, value: string): Promise<void> {
  await applySettingsFieldPreview(page, "general-settings-workspace", fieldLabel, value);
}

/** Reset general settings local preview. */
export async function resetGeneralSettingsPreview(page: Page): Promise<void> {
  await resetSettingsSectionPreview(page, "general-settings-workspace");
}

/** Reset security settings local preview. */
export async function resetSecuritySettingsPreview(page: Page): Promise<void> {
  await resetSettingsSectionPreview(page, "security-settings-workspace");
}

/** Reset notification settings local preview. */
export async function resetNotificationSettingsPreview(page: Page): Promise<void> {
  await resetSettingsSectionPreview(page, "notification-settings-workspace");
}

/** Reset integration settings local preview. */
export async function resetIntegrationSettingsPreview(page: Page): Promise<void> {
  await resetSettingsSectionPreview(page, "integration-settings-workspace");
}

/** Wait until an audit route transition finishes and workspace content is interactive. */
export async function expectAuditReady(page: Page): Promise<void> {
  await expect(page.getByRole("heading", { name: "Audit", level: 1 })).toBeVisible({ timeout: 60_000 });

  const routeLoading = page
    .getByTestId("audit-loading-state")
    .or(page.locator('[aria-busy="true"][aria-label="Loading audit"]'));
  if ((await routeLoading.count()) > 0) {
    await expect(routeLoading.first()).toBeHidden({ timeout: 30_000 });
  }

  await expect(
    page.getByTestId("audit-workspace").or(page.getByText(/Unable to load audit events/i)),
  ).toBeVisible({ timeout: 30_000 });
}

/** Apply audit filters and wait for URL update. */
export async function applyAuditFiltersAndWait(page: Page, urlPattern: RegExp): Promise<void> {
  await expectAuditReady(page);
  const apply = page.getByRole("button", { name: "Apply filters" });
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(apply).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), apply.click()]);
      await expectAuditReady(page);
      return;
    } catch (error) {
      if (attempt === 2) throw error;
      await expectAuditReady(page);
    }
  }
}

/** Click an audit table sort header and wait for URL update. */
export async function clickAuditSortHeader(page: Page, columnLabel: string, sortKey: string): Promise<void> {
  await expectAuditReady(page);
  const table = page.getByTestId("audit-table");
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
      if (attempt === 2) throw error;
      await expectAuditReady(page);
    }
  }
}

/** Reset audit filters and wait for cleared URL state. */
export async function resetAuditFiltersAndWait(page: Page, urlMustNotMatch: RegExp): Promise<void> {
  await expectAuditReady(page);
  await page.getByRole("button", { name: "Reset filters" }).click();
  await expect.poll(() => page.url(), { timeout: 30_000 }).not.toMatch(urlMustNotMatch);
  await expectAuditReady(page);
}

/** Open an audit event drawer from the data table. */
export async function openAuditEventDrawer(page: Page, eventId: string): Promise<void> {
  await expectAuditReady(page);
  const table = page.getByTestId("audit-table");
  await expectTableReady(table);
  const rowButton = table.getByRole("button", { name: eventId });
  const urlPattern = new RegExp(`selected=${eventId.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`);
  for (let attempt = 0; attempt < 3; attempt += 1) {
    await expect(rowButton).toBeVisible();
    await expect(rowButton).toBeEnabled();
    try {
      await Promise.all([page.waitForURL(urlPattern, CMS_URL_WAIT), rowButton.click()]);
      await expect(page.getByRole("dialog")).toBeVisible({ timeout: 15_000 });
      return;
    } catch (error) {
      if (attempt === 2) throw error;
      await expectAuditReady(page);
    }
  }
}

/** Apply audit date preset and wait for URL. */
export async function applyAuditPresetAndWait(page: Page, preset: string, urlPattern: RegExp): Promise<void> {
  await expectAuditReady(page);
  const select = page.locator("#audit-date-preset");
  await selectFilterOption(select, preset);
  await applyAuditFiltersAndWait(page, urlPattern);
}

/** Open audit export preview drawer. */
export async function openAuditExportDrawer(page: Page): Promise<void> {
  await expectAuditReady(page);
  const exportButton = page.getByTestId("audit-export-button");
  await expect(exportButton).toBeEnabled();
  await exportButton.click();
  const dialog = page.getByRole("dialog");
  await expect(dialog).toBeVisible({ timeout: 10_000 });
  await expect(dialog.getByRole("button", { name: "Download CSV" })).toBeVisible();
}
