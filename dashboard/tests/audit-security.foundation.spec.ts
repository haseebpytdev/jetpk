import { test, expect } from "@playwright/test";
import { escapeCsvCell } from "@/lib/csv-safe";
import {
  TEST_NET_PREFIXES,
  VALID_CATEGORIES,
  VALID_CHANNELS,
  VALID_EVENT_TYPES,
  VALID_OUTCOMES,
  VALID_SEVERITIES,
  validateAuditCatalog,
  validateAuditEvent,
} from "@/lib/audit/audit-validation";
import { resolveAuditDatePreset } from "@/lib/audit/date-presets";
import {
  buildAuditExportCsv,
  buildAuditExportManifest,
  exportManifestExcludesSensitiveFields,
} from "@/lib/audit/export-preview";
import { PERMISSION_BY_KEY } from "@/lib/access-control/permission-catalog";
import { mockRoles } from "@/mocks/rbac-fixtures";
import { AUDIT_FIXTURE_COUNT, AUDIT_REFERENCE_DATE, getAuditEventById, mockAuditEvents } from "@/mocks/audit-fixtures";
import { getUserById } from "@/mocks/user-fixtures";
import { ACCESS_BRAND } from "@/types/access-control";
import type { AuditEvent } from "@/types/access-control";

test("audit IDs are unique", () => {
  const ids = mockAuditEvents.map((event) => event.id);
  expect(new Set(ids).size).toBe(ids.length);
});

test("actor references are valid", () => {
  for (const event of mockAuditEvents) {
    if (event.actor.userId) {
      expect(getUserById(event.actor.userId)).toBeTruthy();
    }
  }
});

test("target references are valid", () => {
  const roleIds = new Set(mockRoles.map((role) => role.id));
  for (const event of mockAuditEvents) {
    if (event.target.type === "user") {
      expect(getUserById(event.target.id)).toBeTruthy();
    }
    if (event.target.type === "role") {
      expect(roleIds.has(event.target.id)).toBeTruthy();
    }
    if (event.target.type === "permission" && event.target.id !== "matrix" && event.target.id !== "catalogue") {
      expect(PERMISSION_BY_KEY.has(event.target.id)).toBeTruthy();
    }
  }
});

test("event types are valid", () => {
  for (const event of mockAuditEvents) {
    expect(VALID_EVENT_TYPES.includes(event.type)).toBeTruthy();
  }
});

test("categories are valid", () => {
  for (const event of mockAuditEvents) {
    expect(VALID_CATEGORIES.includes(event.category)).toBeTruthy();
  }
});

test("severities are valid", () => {
  for (const event of mockAuditEvents) {
    expect(VALID_SEVERITIES.includes(event.severity)).toBeTruthy();
  }
});

test("outcomes are valid", () => {
  for (const event of mockAuditEvents) {
    expect(VALID_OUTCOMES.includes(event.outcome)).toBeTruthy();
  }
});

test("channels are valid", () => {
  for (const event of mockAuditEvents) {
    expect(VALID_CHANNELS.includes(event.metadata.channel)).toBeTruthy();
  }
});

test("timestamps are deterministic", () => {
  expect(AUDIT_REFERENCE_DATE).toBe("2026-06-30T12:00:00.000Z");
  const range = resolveAuditDatePreset("last_7_days");
  expect(range.startDate).toBe("2026-06-24");
  expect(range.endDate).toBe("2026-06-30");
  expect(mockAuditEvents[0]!.occurredAt).toBe("2026-06-30T14:22:00.000Z");
  expect(mockAuditEvents[mockAuditEvents.length - 1]!.occurredAt).toBe("2026-06-01T09:00:00.000Z");
});

test("unmasked IP is rejected by validateAuditEvent", () => {
  const base = getAuditEventById("JP-AUD-0001")!;
  const bad: AuditEvent = {
    ...base,
    metadata: {
      ...base.metadata,
      maskedIp: "8.8.8.8",
      maskedNetworkRange: "8.8.8.0/24",
    },
  };
  const result = validateAuditEvent(bad);
  expect(result.valid).toBe(false);
  expect(result.issues.some((issue) => issue.code === "AUDIT_UNMASKED_IP")).toBeTruthy();
});

test("secret metadata is rejected", () => {
  const base = getAuditEventById("JP-AUD-0001")!;
  const bad: AuditEvent = {
    ...base,
    summary: `${base.summary} passwordHash fixture leak`,
  };
  const result = validateAuditEvent(bad);
  expect(result.valid).toBe(false);
  expect(result.issues.some((issue) => issue.code === "AUDIT_SECRET_METADATA")).toBeTruthy();
});

test("duplicate event is rejected", () => {
  const duplicate = mockAuditEvents[0]!;
  const result = validateAuditCatalog([duplicate, duplicate]);
  expect(result.valid).toBe(false);
  expect(result.issues.some((issue) => issue.code === "AUDIT_DUPLICATE_ID")).toBeTruthy();
});

test("fake mutation is detected", () => {
  const base = getAuditEventById("JP-AUD-0001")!;
  const bad: AuditEvent = {
    ...base,
    summary: "Preview attempted live save in dashboard.",
  };
  const result = validateAuditEvent(bad);
  expect(result.valid).toBe(false);
  expect(result.issues.some((issue) => issue.code === "AUDIT_FAKE_MUTATION")).toBeTruthy();
});

test("missing preview marker is detected", () => {
  const base = getAuditEventById("JP-AUD-0011")!;
  const bad: AuditEvent = {
    ...base,
    metadata: {
      ...base.metadata,
      previewOnly: false,
    },
  };
  const result = validateAuditEvent(bad);
  expect(result.valid).toBe(false);
  expect(result.issues.some((issue) => issue.code === "AUDIT_MISSING_PREVIEW_MARKER")).toBeTruthy();
});

test("export manifest excludes sensitive fields", () => {
  const manifest = buildAuditExportManifest(mockAuditEvents);
  expect(exportManifestExcludesSensitiveFields(manifest)).toBe(true);
  expect(manifest.previewOnly).toBe(true);
  expect(manifest.title).toMatch(/JetPakistan/i);
});

test("TEST-NET masking is used for fixture IPs", () => {
  for (const event of mockAuditEvents) {
    const ip = event.metadata.maskedIp;
    if (!ip) continue;
    expect(TEST_NET_PREFIXES.some((prefix) => ip.startsWith(prefix))).toBeTruthy();
  }
});

test("fixture JSON contains no tokens or secrets", () => {
  const body = JSON.stringify(mockAuditEvents);
  expect(body).not.toMatch(/Bearer|sessionId|passwordHash|apiKey|webhookSecret|smtpPassword/i);
});

test("validateAuditCatalog passes for mockAuditEvents", () => {
  const result = validateAuditCatalog(mockAuditEvents);
  expect(result.valid).toBe(true);
  expect(result.issues).toEqual([]);
});

test("fixture count matches expected catalog size", () => {
  expect(AUDIT_FIXTURE_COUNT).toBe(60);
  expect(mockAuditEvents.length).toBe(60);
});

test("CSV export uses safe escaping", () => {
  const csv = buildAuditExportCsv(mockAuditEvents.slice(0, 3));
  expect(csv.split("\r\n").length).toBeGreaterThanOrEqual(4);
  expect(escapeCsvCell("=SUM(A1)")).toBe("'=SUM(A1)");
  expect(csv).not.toMatch(/password|sessionId|Bearer/i);
});

test("JetPakistan brand is fixed", () => {
  expect(ACCESS_BRAND.id).toBe("jetpakistan");
  expect(ACCESS_BRAND.label).toBe("JetPakistan");
});

test("existing users and settings routes remain functional", async ({ request }) => {
  const users = await request.get("/testdash/users", { timeout: 120_000 });
  expect(users.ok()).toBeTruthy();
  const settings = await request.get("/testdash/settings", { timeout: 120_000 });
  expect(settings.ok()).toBeTruthy();
  const audit = await request.get("/testdash/audit", { timeout: 120_000 });
  expect(audit.ok()).toBeTruthy();
});
