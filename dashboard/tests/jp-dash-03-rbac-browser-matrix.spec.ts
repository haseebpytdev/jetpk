import fs from "node:fs";
import path from "node:path";
import {
  adminStorageStateExists,
  expect,
  staffStorageStateExists,
  test,
  staffTest,
} from "./jp-dash-03-acceptance-session";
import { request as playwrightRequest } from "@playwright/test";

const repoRoot = path.resolve(process.cwd(), "..");
const matrixPath = path.join(repoRoot, "docs/jetpk/JP-DASH-03-RBAC-BROWSER-MATRIX.json");

type AccessExpectation = "allow" | "deny";

type RbacProbe = {
  module: string;
  adminRoute?: string;
  staffRoute?: string;
  laravelRoute?: string;
  apiPath?: string;
  staffApiExpected?: AccessExpectation;
  anonymousExpected?: AccessExpectation;
  customerApiExpected?: AccessExpectation;
  agentApiExpected?: AccessExpectation;
};

const PROBES: RbacProbe[] = [
  {
    module: "Dashboard",
    adminRoute: "/admin/dashboard",
    staffRoute: "/staff/dashboard",
    apiPath: "/api/dashboard/overview",
  },
  {
    module: "Bookings",
    adminRoute: "/admin/dashboard/bookings",
    staffRoute: "/staff/dashboard/bookings",
    apiPath: "/api/dashboard/bookings",
  },
  {
    module: "Payments",
    adminRoute: "/admin/dashboard/payments",
    staffRoute: "/staff/dashboard/payments",
    apiPath: "/api/dashboard/payments",
  },
  {
    module: "Customers",
    adminRoute: "/admin/dashboard/customers",
    staffRoute: "/staff/dashboard/customers",
    apiPath: "/api/dashboard/customers",
  },
  {
    module: "Agents",
    adminRoute: "/admin/dashboard/agents",
    staffRoute: "/staff/dashboard/agents",
  },
  {
    module: "Suppliers",
    adminRoute: "/admin/dashboard/suppliers",
    staffRoute: "/staff/dashboard/suppliers",
  },
  {
    module: "Users",
    adminRoute: "/admin/dashboard/users",
    staffRoute: "/staff/dashboard/users",
    staffApiExpected: "deny",
  },
  {
    module: "Staff management",
    laravelRoute: "/admin/staff",
    staffApiExpected: "deny",
    anonymousExpected: "deny",
  },
  {
    module: "API Settings",
    laravelRoute: "/admin/api-settings",
    staffApiExpected: "deny",
    anonymousExpected: "deny",
  },
  {
    module: "Settings",
    adminRoute: "/admin/dashboard/settings",
    staffRoute: "/staff/dashboard/settings",
    laravelRoute: "/admin/settings",
  },
  {
    module: "CMS",
    adminRoute: "/admin/dashboard/cms/pages",
    staffRoute: "/staff/dashboard/cms/pages",
  },
  {
    module: "Reports",
    adminRoute: "/admin/dashboard/reports",
    staffRoute: "/staff/dashboard/reports",
    apiPath: "/api/dashboard/reports/bookings",
  },
  {
    module: "Audit",
    adminRoute: "/admin/dashboard/audit",
    staffRoute: "/staff/dashboard/audit",
  },
  {
    module: "Page Settings",
    laravelRoute: "/admin/page-settings",
    staffApiExpected: "deny",
    anonymousExpected: "deny",
  },
  {
    module: "Go-live checklist",
    laravelRoute: "/admin/go-live-checklist",
    staffApiExpected: "deny",
    anonymousExpected: "deny",
  },
];

const PREVIEW_RESIDUE = /Preview data|Dashboard unavailable|Admin Preview/i;

function classifyHttpStatus(status: number, expected: AccessExpectation): string {
  if (expected === "allow") {
    return status >= 200 && status < 400 ? "PASS" : "FAIL";
  }
  return status === 401 || status === 403 || status === 302 || status === 404 ? "PASS" : "FAIL";
}

async function probePage(
  baseURL: string,
  route: string,
  storagePath?: string,
): Promise<{ status: number; bodyOk: boolean }> {
  const ctx = await playwrightRequest.newContext({
    baseURL,
    storageState: storagePath,
  });
  try {
    const response = await ctx.get(route, { timeout: 120_000 });
    const body = await response.text();
    return {
      status: response.status(),
      bodyOk: !PREVIEW_RESIDUE.test(body),
    };
  } finally {
    await ctx.dispose();
  }
}

async function probeApi(
  baseURL: string,
  apiPath: string,
  storagePath?: string,
): Promise<number> {
  const ctx = await playwrightRequest.newContext({
    baseURL,
    storageState: storagePath,
  });
  try {
    const response = await ctx.get(apiPath, {
      timeout: 60_000,
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
    });
    return response.status();
  } finally {
    await ctx.dispose();
  }
}

test.describe("JP-DASH-03 RBAC browser matrix", () => {
  test.beforeAll(() => {
    if (!adminStorageStateExists()) {
      test.skip(true, "ADMIN_PLAYWRIGHT_SESSION=MISSING");
    }
  });

  test("multi-role production RBAC matrix", async ({ baseURL }) => {
    const rows: Array<Record<string, string>> = [];
    const adminStorage = path.join(repoRoot, "tmp/jp-dash-03-admin-storage-state.json");
    const staffStorage = path.join(repoRoot, "tmp/jp-dash-03-staff-storage-state.json");
    const hasStaff = staffStorageStateExists();

    for (const probe of PROBES) {
      if (probe.adminRoute) {
        const pageProbe = await probePage(baseURL!, probe.adminRoute, adminStorage);
        rows.push({
          module: probe.module,
          role: "ADMIN",
          route: probe.adminRoute,
          control: "direct_url",
          expected_access: "allow",
          result: pageProbe.status < 400 && pageProbe.bodyOk ? "PASS" : "FAIL",
        });
      }

      if (probe.apiPath) {
        const status = await probeApi(baseURL!, probe.apiPath, adminStorage);
        rows.push({
          module: probe.module,
          role: "ADMIN",
          route: probe.apiPath,
          control: "api",
          expected_access: "allow",
          result: classifyHttpStatus(status, "allow"),
        });
      }

      if (probe.laravelRoute) {
        const pageProbe = await probePage(baseURL!, probe.laravelRoute, adminStorage);
        rows.push({
          module: probe.module,
          role: "ADMIN",
          route: probe.laravelRoute,
          control: "laravel_page",
          expected_access: "allow",
          result: pageProbe.status < 400 && pageProbe.bodyOk ? "PASS" : "FAIL",
        });
      }

      if (hasStaff && probe.staffRoute) {
        const pageProbe = await probePage(baseURL!, probe.staffRoute, staffStorage);
        const expected: AccessExpectation = probe.staffApiExpected ?? "allow";
        rows.push({
          module: probe.module,
          role: "STAFF",
          route: probe.staffRoute,
          control: "direct_url",
          expected_access: expected,
          result: classifyHttpStatus(pageProbe.status, expected),
        });
      }

      if (probe.laravelRoute) {
        const anon = await probePage(baseURL!, probe.laravelRoute);
        const expected = probe.anonymousExpected ?? "deny";
        rows.push({
          module: probe.module,
          role: "ANONYMOUS",
          route: probe.laravelRoute,
          control: "direct_url",
          expected_access: expected,
          result: classifyHttpStatus(anon.status, expected),
        });
      }

      if (probe.apiPath) {
        const anonStatus = await probeApi(baseURL!, probe.apiPath);
        rows.push({
          module: probe.module,
          role: "ANONYMOUS",
          route: probe.apiPath,
          control: "api",
          expected_access: "deny",
          result: classifyHttpStatus(anonStatus, "deny"),
        });
      }
    }

    const failures = rows.filter((row) => row.result === "FAIL");
    const matrix = {
      generatedAtUtc: new Date().toISOString(),
      staffSessionAvailable: hasStaff,
      multiRoleRbacBrowserMatrix: failures.length === 0 ? "PASS" : "PARTIAL",
      rows,
    };

    fs.mkdirSync(path.dirname(matrixPath), { recursive: true });
    fs.writeFileSync(matrixPath, JSON.stringify(matrix, null, 2));

    expect(failures, JSON.stringify(failures)).toHaveLength(0);
  });
});

staffTest.describe("JP-DASH-03 staff RBAC smoke", () => {
  staffTest.beforeAll(() => {
    if (!staffStorageStateExists()) {
      staffTest.skip(true, "STAFF_PLAYWRIGHT_SESSION=MISSING");
    }
  });

  staffTest("staff cannot access admin portal session API", async ({ baseURL }) => {
    const staffStorage = path.join(repoRoot, "tmp/jp-dash-03-staff-storage-state.json");
    const status = await probeApi(baseURL!, "/api/dashboard/session?portal=admin", staffStorage);
    expect(status === 403 || status === 401).toBeTruthy();
  });
});
