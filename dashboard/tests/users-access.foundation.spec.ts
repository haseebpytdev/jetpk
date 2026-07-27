import { test, expect } from "@playwright/test";
import { evaluateAccessDecision, evaluateMultiRoleAccess } from "@/lib/access-control/access-decision";
import { validateRoleAssignmentPreview, validateUser } from "@/lib/access-control/access-validation";
import { PERMISSION_CATALOG, isHighRiskPermission } from "@/lib/access-control/permission-catalog";
import { mockRolePermissions, mockRoles } from "@/mocks/rbac-fixtures";
import { getUserById, mockUsers } from "@/mocks/user-fixtures";
import { ACCESS_BRAND } from "@/types/access-control";

test("user IDs are unique", () => {
  const ids = mockUsers.map((u) => u.id);
  expect(new Set(ids).size).toBe(ids.length);
});

test("role IDs are unique", () => {
  const ids = mockRoles.map((r) => r.id);
  expect(new Set(ids).size).toBe(ids.length);
});

test("permission IDs and keys are unique", () => {
  const ids = PERMISSION_CATALOG.map((p) => p.id);
  const keys = PERMISSION_CATALOG.map((p) => p.key);
  expect(new Set(ids).size).toBe(ids.length);
  expect(new Set(keys).size).toBe(keys.length);
});

test("role assignments reference valid roles", () => {
  const roleIds = new Set(mockRoles.map((r) => r.id));
  for (const user of mockUsers) {
    for (const assignment of user.assignedRoles) {
      expect(roleIds.has(assignment.roleId)).toBeTruthy();
    }
  }
});

test("role permissions reference valid permissions", () => {
  const permissionKeys = new Set(PERMISSION_CATALOG.map((p) => p.key));
  for (const rp of mockRolePermissions) {
    expect(permissionKeys.has(rp.permissionKey)).toBeTruthy();
  }
});

test("protected roles are marked", () => {
  const protectedRoles = mockRoles.filter((r) => r.isProtected);
  expect(protectedRoles.length).toBeGreaterThanOrEqual(2);
  expect(protectedRoles.every((r) => r.isSystem)).toBeTruthy();
});

test("high-risk permissions are classified", () => {
  const highRisk = PERMISSION_CATALOG.filter((p) => p.isHighRisk);
  expect(highRisk.length).toBeGreaterThanOrEqual(10);
  expect(highRisk.every((p) => isHighRiskPermission(p.key))).toBeTruthy();
});

test("permission groups are valid", () => {
  const domains = new Set(PERMISSION_CATALOG.map((p) => p.domain));
  expect(domains.has("bookings")).toBeTruthy();
  expect(domains.has("users")).toBeTruthy();
  expect(domains.has("audit")).toBeTruthy();
});

test("unknown permission is denied", () => {
  const user = getUserById("JP-USR-0001")!;
  const decision = evaluateAccessDecision({
    user,
    roleIds: user.assignedRoles.map((r) => r.roleId),
    permissionKey: "unknown.permission",
  });
  expect(decision.allowed).toBe(false);
  expect(decision.reason).toBe("denied_no_permission");
});

test("access decision returns source role", () => {
  const user = getUserById("JP-USR-0003")!;
  const decision = evaluateAccessDecision({
    user,
    roleIds: user.assignedRoles.map((r) => r.roleId),
    permissionKey: "bookings.view",
  });
  expect(decision.allowed).toBe(true);
  expect(decision.sourceRoleId).toBe("JP-ROL-0003");
});

test("no-role user is denied", () => {
  const user = getUserById("JP-USR-0012")!;
  const decision = evaluateAccessDecision({
    user,
    roleIds: [],
    permissionKey: "dashboard.view",
  });
  expect(decision.allowed).toBe(false);
  expect(decision.reason).toBe("denied_no_role");
});

test("multi-role access is combined deterministically", () => {
  const user = getUserById("JP-USR-0004")!;
  const roleIds = user.assignedRoles.map((r) => r.roleId);
  const decision = evaluateMultiRoleAccess(user, roleIds, "pnrs.view");
  expect(decision.allowed).toBe(true);
});

test("duplicate role validation works", () => {
  const user = getUserById("JP-USR-0037")!;
  const result = validateUser(user);
  expect(result.issues.some((i) => i.code === "USER_DUPLICATE_ROLE")).toBeTruthy();
});

test("excessive-risk validation works", () => {
  const user = getUserById("JP-USR-0019")!;
  const result = validateUser(user);
  expect(result.issues.some((i) => i.code === "USER_EXCESSIVE_HIGH_RISK")).toBeTruthy();
});

test("suspended-session inconsistency is detected", () => {
  const user = getUserById("JP-USR-0015")!;
  const result = validateUser(user);
  expect(result.issues.some((i) => i.code === "USER_SUSPENDED_ACTIVE_SESSION")).toBeTruthy();
});

test("MFA-required violation is detected", () => {
  const user = getUserById("JP-USR-0023")!;
  const result = validateUser(user);
  expect(result.issues.some((i) => i.code === "USER_MFA_REQUIRED_DISABLED")).toBeTruthy();
});

test("role preview duplicate validation works", () => {
  const issues = validateRoleAssignmentPreview("JP-USR-0001", ["JP-ROL-0001"], ["JP-ROL-0001", "JP-ROL-0001"]);
  expect(issues.some((i) => i.code === "PREVIEW_DUPLICATE_ROLE")).toBeTruthy();
});

test("JetPakistan brand is fixed", () => {
  expect(ACCESS_BRAND.id).toBe("jetpakistan");
  expect(ACCESS_BRAND.label).toBe("JetPakistan");
});

test("no brand switch exists", () => {
  const body = JSON.stringify({ users: mockUsers.slice(0, 3), roles: mockRoles.slice(0, 3) });
  expect(body).not.toMatch(/Parwaaz|YoursDomain|haseeb-master/i);
});

test("fixture counts within expected range", () => {
  expect(mockUsers.length).toBeGreaterThanOrEqual(36);
  expect(mockUsers.length).toBeLessThanOrEqual(48);
  expect(mockRoles.length).toBeGreaterThanOrEqual(10);
  expect(PERMISSION_CATALOG.length).toBeGreaterThanOrEqual(45);
});

test("existing critical regression routes remain valid", async ({ request }) => {
  const response = await request.get("/testdash", { timeout: 120_000 });
  expect(response.ok()).toBeTruthy();
});
