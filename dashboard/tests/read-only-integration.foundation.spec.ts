import { test, expect } from "@playwright/test";
import {
  DATA_SOURCE_MODES,
  DATA_SOURCE_STATES,
  READ_ONLY_ERROR_CODES,
  READ_ONLY_SCHEMA_VERSION,
} from "@/types/read-only-integration";
import {
  isValidDataSourceMode,
  isValidDataSourceState,
  resolveDataSourceMode,
  buildFixtureMetadata,
  buildLaravelMetadata,
  isStaleMetadata,
} from "@/lib/read-only/data-source";
import { createReadOnlyEnvelope, isReadOnlyEnvelope } from "@/lib/read-only/response-envelope";
import {
  createReadOnlyErrorEnvelope,
  sanitizeErrorMessage,
  mapHttpStatusToErrorCode,
} from "@/lib/read-only/error-envelope";
import {
  assertReadOnlyHttpMethod,
  READ_ONLY_HTTP_METHODS,
  createReadOnlyService,
  ReadOnlyServiceError,
} from "@/lib/read-only/read-only-service";
import { containsSensitiveKeys, stripSensitiveFields, SENSITIVE_FIELD_KEYS } from "@/lib/read-only/sensitive-fields";
import { READ_ONLY_ENDPOINT_CONTRACTS, getEndpointContract } from "@/lib/read-only/endpoint-contracts";

test("data source modes are valid", () => {
  expect(DATA_SOURCE_MODES).toEqual(["fixture", "laravelReadOnly", "unavailable"]);
  for (const mode of DATA_SOURCE_MODES) {
    expect(isValidDataSourceMode(mode)).toBe(true);
  }
  expect(isValidDataSourceMode("hybrid")).toBe(false);
});

test("data source states are valid", () => {
  expect(DATA_SOURCE_STATES.length).toBe(8);
  for (const state of DATA_SOURCE_STATES) {
    expect(isValidDataSourceState(state)).toBe(true);
  }
});

test("fixture source is explicit in preview mode", () => {
  expect(resolveDataSourceMode()).toBe("fixture");
  const meta = buildFixtureMetadata({ recordCount: 10 });
  expect(meta.source).toBe("fixture");
  expect(meta.fixtureRevision).toBeTruthy();
});

test("laravel metadata marks live read-only source", () => {
  const meta = buildLaravelMetadata({ recordCount: 5, requestIdSafe: "SAFE-001" });
  expect(meta.source).toBe("laravelReadOnly");
  expect(meta.fixtureRevision).toBeNull();
});

test("read-only service does not silently fall back to fixtures when live adapter missing", async () => {
  const service = createReadOnlyService({
    module: "bookings",
    fixtureAdapter: {
      mode: "fixture",
      fetch: async () => createReadOnlyEnvelope({ data: { ok: true } }),
    },
  });

  const originalEnv = process.env.NEXT_PUBLIC_USE_MOCK_DATA;
  process.env.NEXT_PUBLIC_USE_MOCK_DATA = "false";
  try {
    await expect(service.fetchReadOnly({})).rejects.toBeInstanceOf(ReadOnlyServiceError);
  } finally {
    process.env.NEXT_PUBLIC_USE_MOCK_DATA = originalEnv;
  }
});

test("no mutation HTTP methods are allowed", () => {
  expect(READ_ONLY_HTTP_METHODS).toEqual(["GET"]);
  expect(() => assertReadOnlyHttpMethod("GET")).not.toThrow();
  expect(() => assertReadOnlyHttpMethod("POST")).toThrow(/prohibits/);
  expect(() => assertReadOnlyHttpMethod("DELETE")).toThrow(/prohibits/);
});

test("response envelope is typed and versioned", () => {
  const envelope = createReadOnlyEnvelope({ data: { items: [] }, metadata: { recordCount: 0 } });
  expect(isReadOnlyEnvelope(envelope)).toBe(true);
  expect(envelope.schemaVersion).toBe(READ_ONLY_SCHEMA_VERSION);
  expect(envelope.source).toBe("fixture");
  expect(envelope.generatedAt).toBeTruthy();
});

test("error envelope is sanitized", () => {
  const envelope = createReadOnlyErrorEnvelope({
    code: "internal_error",
    referenceIdSafe: "ERR-001",
  });
  expect(envelope.error.message).not.toMatch(/sql|stack|password/i);
  expect(envelope.error.referenceIdSafe).toBe("ERR-001");
  expect(READ_ONLY_ERROR_CODES).toContain(envelope.error.code);
});

test("sanitizeErrorMessage blocks sensitive internals", () => {
  expect(sanitizeErrorMessage("SQLSTATE[HY000] connection failed")).toMatch(/Something went wrong/);
  expect(sanitizeErrorMessage("vendor/laravel/framework/src/")).toMatch(/Something went wrong/);
  expect(sanitizeErrorMessage("Booking not found")).toBe("Booking not found");
});

test("HTTP status maps to safe error codes", () => {
  expect(mapHttpStatusToErrorCode(401)).toBe("unauthenticated");
  expect(mapHttpStatusToErrorCode(403)).toBe("forbidden");
  expect(mapHttpStatusToErrorCode(503)).toBe("unavailable");
});

test("stale metadata detection works", () => {
  const past = new Date(Date.now() - 60_000).toISOString();
  expect(isStaleMetadata(buildLaravelMetadata({ staleAfter: past }))).toBe(true);
  const future = new Date(Date.now() + 60_000).toISOString();
  expect(isStaleMetadata(buildLaravelMetadata({ staleAfter: future }))).toBe(false);
});

test("sensitive fields are excluded from payloads", () => {
  const dirty = { id: "JP-001", password: "secret", pcc: "ABC1" };
  const clean = stripSensitiveFields(dirty);
  expect(clean).toEqual({ id: "JP-001" });
  expect(containsSensitiveKeys(dirty)).toBe(true);
  expect(containsSensitiveKeys(clean)).toBe(false);
  expect(SENSITIVE_FIELD_KEYS).toContain("lniata");
});

test("GDS and NDC endpoint contracts remain distinct", () => {
  const pnrs = getEndpointContract("pnrs");
  expect(pnrs?.cacheStaleBehavior).toMatch(/GDS\/NDC/);
  const reports = getEndpointContract("reports");
  expect(reports?.routeConcept).toContain("/reports/");
  expect(READ_ONLY_ENDPOINT_CONTRACTS.every((c) => c.method === "GET")).toBe(true);
});

const SENSITIVE_LOCAL_STORAGE_KEYS = /^(token|auth|session|permission|role|csrf)/i;

test("no auth token storage in dashboard source", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings", { waitUntil: "load" });
  const storage = await page.evaluate(() => ({
    local: Object.keys(localStorage),
    session: Object.keys(sessionStorage),
  }));
  const sensitiveLocal = storage.local.filter((key) => SENSITIVE_LOCAL_STORAGE_KEYS.test(key));
  const sensitiveSession = storage.session.filter((key) => SENSITIVE_LOCAL_STORAGE_KEYS.test(key));
  expect(sensitiveLocal).toEqual([]);
  expect(sensitiveSession).toEqual([]);
});

test("fixture preview notice renders via query gate", async ({ page }) => {
  await page.goto("/admin/dashboard/bookings?dataSourcePreview=fixture", { waitUntil: "load" });
  await expect(page.getByTestId("data-source-preview-gate")).toBeVisible();
  await expect(page.getByTestId("fixture-data-notice").first()).toBeVisible();
});

test("live read-only notice renders via query gate", async ({ page }) => {
  await page.goto("/admin/dashboard/users?dataSourcePreview=live", { waitUntil: "load" });
  await expect(page.getByTestId("live-readonly-notice")).toBeVisible();
});

test("stale notice renders via query gate", async ({ page }) => {
  await page.goto("/admin/dashboard/reports?dataSourcePreview=stale", { waitUntil: "load" });
  await expect(page.getByTestId("stale-data-notice")).toBeVisible();
});

test("unauthorized state renders via query gate", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?dataSourcePreview=unauthorized", { waitUntil: "load" });
  await expect(page.getByRole("alert").filter({ hasText: /Sign in required/i })).toBeVisible();
});

test("forbidden state renders via query gate", async ({ page }) => {
  await page.goto("/admin/dashboard/audit?dataSourcePreview=forbidden", { waitUntil: "load" });
  await expect(page.getByRole("alert").filter({ hasText: /Access denied/i })).toBeVisible();
});

test("unavailable state renders via query gate", async ({ page }) => {
  await page.goto("/admin/dashboard?dataSourcePreview=unavailable", { waitUntil: "load" });
  const alert = page.getByRole("alert").filter({ hasText: /Service unavailable/i });
  await expect(alert).toBeVisible();
  await expect(alert).toContainText(/not shown as a fallback/i);
});

test("metadata summary renders via query gate", async ({ page }) => {
  await page.goto("/admin/dashboard?dataSourcePreview=metadata", { waitUntil: "load" });
  await expect(page.getByTestId("data-source-metadata-summary")).toContainText("Fixture preview");
});
