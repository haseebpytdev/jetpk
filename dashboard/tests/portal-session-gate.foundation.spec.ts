import { test, expect } from "@playwright/test";
import {
  assertPortalSessionAuthorized,
  DashboardPortalSessionError,
} from "@/lib/portal-session-gate";
import type { DashboardSessionSummary } from "@/services/session-service";

function session(overrides: Partial<DashboardSessionSummary>): DashboardSessionSummary {
  return {
    id: "1",
    displayName: "Test User",
    email: "test@example.com",
    roles: [],
    permissions: [],
    accountType: "customer",
    accountStatus: "active",
    portalType: "admin",
    platformRole: "customer",
    sessionUsable: true,
    denialReason: null,
    requiresPasswordChange: false,
    requiresEmailVerification: false,
    landingRoute: "/customer/dashboard",
    navigation: [],
    capabilities: {},
    initials: "TU",
    ...overrides,
  };
}

test("customer admin portal session is rejected", () => {
  expect(() =>
    assertPortalSessionAuthorized(
      session({ accountType: "customer", platformRole: "customer", portalType: "admin" }),
      "admin",
    ),
  ).toThrow(DashboardPortalSessionError);
});

test("staff admin portal session is rejected", () => {
  expect(() =>
    assertPortalSessionAuthorized(
      session({ accountType: "platform_staff", portalType: "admin", platformRole: "staff" }),
      "admin",
    ),
  ).toThrow(DashboardPortalSessionError);
});

test("platform admin admin portal session is allowed", () => {
  expect(() =>
    assertPortalSessionAuthorized(
      session({ accountType: "platform_admin", portalType: "admin", platformRole: "platform_admin" }),
      "admin",
    ),
  ).not.toThrow();
});

test("staff portal mismatch is rejected", () => {
  expect(() =>
    assertPortalSessionAuthorized(session({ accountType: "platform_staff", portalType: "staff" }), "admin"),
  ).toThrow(DashboardPortalSessionError);
});

test("unusable session is rejected", () => {
  expect(() => assertPortalSessionAuthorized(session({ sessionUsable: false, unavailable: true }), "admin")).toThrow(
    DashboardPortalSessionError,
  );
});
