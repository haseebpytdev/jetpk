import { test, expect } from "@playwright/test";

/**
 * Unit coverage for Integrations ApiResult → flattened domain contract.
 * Mirrors unwrapLaravelData() in dashboard/services/operational-api.ts.
 */
function unwrapLaravelDataForTest<T extends Record<string, unknown>>(
  result:
    | { ok: true; data: T; status: number }
    | { ok: false; message: string; code?: string; status: number },
): { ok: boolean; message?: string; status?: number } & Partial<T> {
  if (!result.ok) {
    return { ok: false, message: result.message, status: result.status } as {
      ok: boolean;
      message?: string;
      status?: number;
    } & Partial<T>;
  }
  const payload = (result.data ?? {}) as T;
  const payloadMessage = (payload as Record<string, unknown>).message;
  return {
    ...payload,
    ok: true,
    status: result.status,
    message: typeof payloadMessage === "string" ? payloadMessage : undefined,
  };
}

test("integrations list ApiResult unwrap exposes hub for workspace metrics", () => {
  const result = unwrapLaravelDataForTest({
    ok: true,
    status: 200,
    data: {
      ok: true,
      hub: { metrics: { total: 13 }, integrations: [{ code: "sabre" }] },
      permissions: { view: true, manage: true },
    },
  });

  expect(result.ok).toBe(true);
  expect(result.hub?.metrics?.total).toBe(13);
  expect(result.permissions?.view).toBe(true);
  expect((result as { data?: unknown }).data).toBeUndefined();
});

test("integrations detail ApiResult unwrap exposes integration for Settings", () => {
  const result = unwrapLaravelDataForTest({
    ok: true,
    status: 200,
    data: {
      ok: true,
      integration: { code: "abhipay", name: "AbhiPay", settings: { values: {} } },
    },
  });

  expect(result.ok).toBe(true);
  expect(result.integration?.code).toBe("abhipay");
});

test("integrations mutation ApiResult unwrap exposes integration", () => {
  const result = unwrapLaravelDataForTest({
    ok: true,
    status: 200,
    data: {
      ok: true,
      message: "Saved",
      integration: { code: "sabre", active: true },
    },
  });

  expect(result.ok).toBe(true);
  expect(result.message).toBe("Saved");
  expect(result.integration?.code).toBe("sabre");
});

test("integrations activation ApiResult unwrap exposes integration", () => {
  const result = unwrapLaravelDataForTest({
    ok: true,
    status: 200,
    data: {
      ok: true,
      integration: { code: "pia", active: false },
    },
  });

  expect(result.ok).toBe(true);
  expect(result.integration?.active).toBe(false);
});

test("integrations test-connection ApiResult unwrap exposes result", () => {
  const result = unwrapLaravelDataForTest({
    ok: true,
    status: 200,
    data: {
      ok: true,
      result: { success: true, latency_ms: 12 },
      integration: { code: "sabre" },
    },
  });

  expect(result.ok).toBe(true);
  expect(result.result?.success).toBe(true);
  expect(result.integration?.code).toBe("sabre");
});

test("integrations test-payment ApiResult unwrap exposes result envelope", () => {
  const result = unwrapLaravelDataForTest({
    ok: true,
    status: 200,
    data: {
      ok: true,
      result: { success: false, message: "not configured" },
      integration: { code: "abhipay" },
    },
  });

  expect(result.ok).toBe(true);
  expect(result.result?.success).toBe(false);
  expect(result.integration?.code).toBe("abhipay");
});

test("integrations unwrap preserves failure without inventing hub", () => {
  const result = unwrapLaravelDataForTest({
    ok: false,
    status: 403,
    message: "Forbidden",
    code: "forbidden",
  });

  expect(result.ok).toBe(false);
  expect(result.message).toBe("Forbidden");
  expect(result.hub).toBeUndefined();
});
