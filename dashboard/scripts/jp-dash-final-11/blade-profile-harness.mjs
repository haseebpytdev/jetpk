/**
 * Portal profile mutation harness (Next dashboard + Blade fallback).
 */
export const BLADE_PROFILE_MARKER_PREFIX = "JP-W03-";

function fieldLocators(page) {
  return {
    agentWhatsapp: page.locator('[data-testid="agent-profile-form"] input[name="whatsapp"]'),
    customerCity: page.locator('[data-testid="customer-profile-form"] input[name="city"]'),
    bladeWhatsapp: page.locator("#whatsapp"),
  };
}

async function resolveProfileField(page, route) {
  await page
    .locator(
      '[data-testid="agent-profile-form"], [data-testid="customer-profile-form"], [data-testid="jp-portal-profile-settings"], #whatsapp',
    )
    .first()
    .waitFor({ state: "visible", timeout: 60000 })
    .catch(() => {});

  const loc = fieldLocators(page);
  if (String(route).includes("/customer/") && (await loc.customerCity.count())) {
    await loc.customerCity.waitFor({ state: "visible", timeout: 30000 });
    return { field: loc.customerCity, fieldName: "city", surface: "next_customer" };
  }
  if ((await loc.agentWhatsapp.count()) > 0) {
    await loc.agentWhatsapp.waitFor({ state: "visible", timeout: 30000 });
    return { field: loc.agentWhatsapp, fieldName: "whatsapp", surface: "next_agent" };
  }
  if ((await loc.bladeWhatsapp.count()) > 0) {
    await loc.bladeWhatsapp.waitFor({ state: "visible", timeout: 30000 });
    return { field: loc.bladeWhatsapp, fieldName: "whatsapp", surface: "blade" };
  }
  throw new Error("profile_mutation_field_missing");
}

export async function readBladeWhatsapp(page, route) {
  const { field } = await resolveProfileField(page, route);
  return field.inputValue();
}

export async function readBladeWhatsappApi(page, route, fieldName) {
  const path = String(route || "").includes("/customer/")
    ? "/laravel/customer/profile?format=json"
    : String(route || "").includes("/agent/")
      ? "/laravel/agent/profile?format=json"
      : "/laravel/profile?format=json";
  return page.evaluate(
    async ({ apiPath, name }) => {
      const res = await fetch(apiPath, {
        headers: { Accept: "application/json" },
        credentials: "include",
      });
      if (!res.ok) {
        return { ok: false, status: res.status };
      }
      const data = await res.json();
      const personal = data?.personal_profile ?? data?.profile ?? data?.data?.personal_profile ?? {};
      const value = personal?.[name] ?? data?.profile?.[name] ?? "";
      return { ok: true, value: String(value) };
    },
    { apiPath: path, name: fieldName },
  );
}

export async function runBladeProfileMutation(page, caseRow, { markerPrefix = BLADE_PROFILE_MARKER_PREFIX } = {}) {
  await page.waitForTimeout(2500);
  const { field, fieldName, surface } = await resolveProfileField(page, caseRow.ROUTE);

  const beforeUi = await field.inputValue();
  const beforeApi = await readBladeWhatsappApi(page, caseRow.ROUTE, fieldName);
  const marker = `${markerPrefix}${caseRow.FUNCTIONAL_CASE_ID.replace("FC-", "")}`;
  const mutated = beforeUi.includes(markerPrefix) ? beforeUi : `${beforeUi} ${marker}`.trim();

  await field.fill(mutated);
  const saveBtn = page.getByRole("button", { name: /^Save profile$/i });
  const responsePromise = page.waitForResponse(
    (r) => {
      const m = r.request().method();
      return (m === "POST" || m === "PATCH" || m === "PUT") && /profile/i.test(r.url());
    },
    { timeout: 45000 },
  );
  await saveBtn.click();
  const http = await responsePromise.catch(() => null);
  const httpStatus = http ? http.status() : 0;
  await page
    .getByText(/profile (saved|updated) successfully/i)
    .waitFor({ state: "visible", timeout: 25000 })
    .catch(() => {});

  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(3000);
  const { field: fieldAfter, fieldName: nameAfter } = await resolveProfileField(page, caseRow.ROUTE);
  const afterReloadUi = await fieldAfter.inputValue();
  const afterApi = await readBladeWhatsappApi(page, caseRow.ROUTE, nameAfter);
  const persisted =
    afterReloadUi.includes(marker) &&
    (afterApi.ok ? afterApi.value.includes(marker) : afterReloadUi.includes(marker));

  const { field: fieldRestore } = await resolveProfileField(page, caseRow.ROUTE);
  await fieldRestore.fill(beforeUi);
  const restorePromise = page.waitForResponse(
    (r) => {
      const m = r.request().method();
      return (m === "POST" || m === "PATCH" || m === "PUT") && /profile/i.test(r.url());
    },
    { timeout: 45000 },
  );
  await saveBtn.click();
  await restorePromise.catch(() => null);
  await page.waitForTimeout(1000);
  await page.reload({ waitUntil: "domcontentloaded" });
  await page.waitForTimeout(2500);
  const { field: fieldFinal, fieldName: nameFinal } = await resolveProfileField(page, caseRow.ROUTE);
  const restoredUi = await fieldFinal.inputValue();
  const restoredApi = await readBladeWhatsappApi(page, caseRow.ROUTE, nameFinal);
  const restored =
    restoredUi === beforeUi &&
    (restoredApi.ok ? restoredApi.value === beforeApi.value : restoredUi === beforeUi);

  return {
    ok: persisted && restored && httpStatus >= 200 && httpStatus < 300,
    beforeUi,
    mutated,
    afterReloadUi,
    restoredUi,
    httpStatus,
    persisted,
    restored,
    beforeApi,
    afterApi,
    restoredApi,
    surface,
    fieldName,
  };
}
