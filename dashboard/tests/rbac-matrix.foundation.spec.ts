import { test, expect } from "@playwright/test";
import { evaluateAccessDecision, evaluateMultiRoleAccess } from "@/lib/access-control/access-decision";
import { buildEffectiveAccessSummary } from "@/lib/access-control/effective-access";
import {
  diffPermissionKeys,
  validatePermissionAssignmentPreview,
} from "@/lib/access-control/permission-preview-validation";
import { PERMISSION_CATALOG, PERMISSION_BY_KEY, getPermissionByKey } from "@/lib/access-control/permission-catalog";
import { compareRoles, formatRoleComparisonNote } from "@/lib/access-control/role-comparison";
import { getRolePermissionKeys } from "@/lib/roles/query-filters";
import { mockRolePermissions, mockRoles } from "@/mocks/rbac-fixtures";
import { getUserById } from "@/mocks/user-fixtures";

const ROLE_ID = "JP-ROL-0001";
const PROTECTED_ROLE = "JP-ROL-0009";
const BOOKINGS_VIEW_ID = "JP-PRM-0002";

test("fixture role count is 14", () => {
  expect(mockRoles).toHaveLength(14);
});

test("fixture permission count is 46", () => {
  expect(PERMISSION_CATALOG).toHaveLength(46);
});

test("bookings.view permission id is stable", () => {
  const permission = getPermissionByKey("bookings.view");
  expect(permission?.id).toBe(BOOKINGS_VIEW_ID);
});

test("super administrator has full permission matrix", () => {
  const keys = getRolePermissionKeys(ROLE_ID);
  expect(keys).toHaveLength(46);
  expect(keys).toContain("bookings.view");
});

test("protected read-only auditor role is marked", () => {
  const role = mockRoles.find((r) => r.id === PROTECTED_ROLE);
  expect(role?.isProtected).toBe(true);
  expect(role?.name).toBe("Read-only Auditor");
});

test("compareRoles returns metrics for two valid roles", () => {
  const result = compareRoles(ROLE_ID, "JP-ROL-0003");
  expect(result).not.toBeNull();
  expect(result!.permissionCountA).toBe(46);
  expect(result!.permissionCountB).toBeLessThan(result!.permissionCountA);
  expect(result!.shared.length).toBeGreaterThan(0);
});

test("compareRoles returns null for unknown role ids", () => {
  expect(compareRoles("JP-ROL-9999", ROLE_ID)).toBeNull();
});

test("role comparison note describes fixture differences", () => {
  const result = compareRoles(ROLE_ID, "JP-ROL-0005")!;
  const note = formatRoleComparisonNote(result);
  expect(note).toMatch(/Permission counts and coverage differ/);
});

test("compareRoles identifies unique and shared permissions", () => {
  const result = compareRoles("JP-ROL-0003", "JP-ROL-0005")!;
  expect(result.uniqueToA.some((k) => k.startsWith("bookings."))).toBeTruthy();
  expect(result.uniqueToB.some((k) => k.startsWith("payments."))).toBeTruthy();
  expect(result.shared).toContain("dashboard.view");
});

test("effective access summary aggregates domains", () => {
  const summary = buildEffectiveAccessSummary([ROLE_ID]);
  expect(summary.domains.length).toBeGreaterThanOrEqual(10);
  expect(summary.highRiskPermissions.length).toBeGreaterThan(0);
});

test("effective access for booking agent is narrower", () => {
  const summary = buildEffectiveAccessSummary(["JP-ROL-0003"]);
  const domainKeys = summary.domains.map((d) => d.domain);
  expect(domainKeys).toContain("bookings");
  expect(domainKeys).not.toContain("audit");
});

test("access decision allows bookings.view for booking agent user", () => {
  const user = getUserById("JP-USR-0003")!;
  const decision = evaluateAccessDecision({
    user,
    roleIds: user.assignedRoles.map((r) => r.roleId),
    permissionKey: "bookings.view",
  });
  expect(decision.allowed).toBe(true);
  expect(decision.sourceRoleId).toBe("JP-ROL-0003");
});

test("access decision denies unknown permission", () => {
  const user = getUserById("JP-USR-0001")!;
  const decision = evaluateAccessDecision({
    user,
    roleIds: user.assignedRoles.map((r) => r.roleId),
    permissionKey: "unknown.permission",
  });
  expect(decision.allowed).toBe(false);
  expect(decision.reason).toBe("denied_no_permission");
});

test("multi-role access combines permissions deterministically", () => {
  const user = getUserById("JP-USR-0028")!;
  const roleIds = user.assignedRoles.map((r) => r.roleId);
  const decision = evaluateMultiRoleAccess(user, roleIds, "audit.view");
  expect(decision.allowed).toBe(true);
});

test("permission preview diff detects additions", () => {
  const fixtureKeys = getRolePermissionKeys("JP-ROL-0003");
  const previewKeys = [...fixtureKeys, "reports.view"];
  const diff = diffPermissionKeys(fixtureKeys, previewKeys);
  expect(diff.added).toEqual(["reports.view"]);
  expect(diff.removed).toEqual([]);
});

test("permission preview diff detects removals", () => {
  const fixtureKeys = getRolePermissionKeys("JP-ROL-0003");
  const previewKeys = fixtureKeys.filter((k) => k !== "bookings.create");
  const diff = diffPermissionKeys(fixtureKeys, previewKeys);
  expect(diff.removed).toContain("bookings.create");
});

test("protected role preview warns on permission additions", () => {
  const fixtureKeys = getRolePermissionKeys(PROTECTED_ROLE);
  const issues = validatePermissionAssignmentPreview(PROTECTED_ROLE, fixtureKeys, [...fixtureKeys, "users.view"]);
  expect(issues.some((i) => i.code === "PREVIEW_PROTECTED_ROLE")).toBeTruthy();
});

test("permission preview flags missing prerequisite", () => {
  const fixtureKeys = getRolePermissionKeys("JP-ROL-0003");
  const issues = validatePermissionAssignmentPreview("JP-ROL-0003", fixtureKeys, [...fixtureKeys, "bookings.cancel.approve"]);
  expect(issues.some((i) => i.code === "PREVIEW_MISSING_PREREQUISITE")).toBeTruthy();
});

test("permission preview duplicate keys are blocked", () => {
  const fixtureKeys = getRolePermissionKeys("JP-ROL-0003");
  const issues = validatePermissionAssignmentPreview(
    "JP-ROL-0003",
    fixtureKeys,
    [...fixtureKeys, fixtureKeys[0]],
  );
  expect(issues.some((i) => i.code === "PREVIEW_DUPLICATE_PERMISSION")).toBeTruthy();
});

test("role permission map references valid permission keys", () => {
  const keys = new Set(PERMISSION_CATALOG.map((p) => p.key));
  for (const rp of mockRolePermissions) {
    expect(keys.has(rp.permissionKey)).toBeTruthy();
  }
});

test("matrix domain filter reduces permission catalog slice", () => {
  const bookings = PERMISSION_CATALOG.filter((p) => p.domain === "bookings");
  expect(bookings.length).toBeGreaterThan(0);
  expect(bookings.every((p) => PERMISSION_BY_KEY.has(p.key))).toBeTruthy();
});

test("high-risk permissions are catalogued", () => {
  const highRisk = PERMISSION_CATALOG.filter((p) => p.isHighRisk);
  expect(highRisk.length).toBeGreaterThanOrEqual(10);
  expect(highRisk.some((p) => p.key === "roles.assignPermissions")).toBeTruthy();
});

test("roles route remains reachable", async ({ request }) => {
  const response = await request.get("/admin/dashboard/users/roles", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

test("permissions route remains reachable", async ({ request }) => {
  const response = await request.get("/admin/dashboard/users/permissions", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

test("settings route remains reachable", async ({ request }) => {
  const response = await request.get("/admin/dashboard/settings", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});

test("rbac fixtures contain no legacy brand strings", () => {
  const body = JSON.stringify({ roles: mockRoles, permissions: PERMISSION_CATALOG.slice(0, 5) });
  expect(body).not.toMatch(/Parwaaz|YoursDomain|haseeb-master/i);
});
