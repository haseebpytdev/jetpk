import { test, expect } from "@playwright/test";
import { getDashboardMode, mutationsAllowed, useMockData } from "@/lib/preview";
import { resolveDataSourceMode, mapModeToLabel } from "@/lib/read-only/data-source";
import { emptyListDescription } from "@/lib/empty-list-copy";

function withEnv(vars: Record<string, string | undefined>, run: () => void) {
  const previous: Record<string, string | undefined> = {};
  for (const key of Object.keys(vars)) {
    previous[key] = process.env[key];
    const next = vars[key];
    if (next === undefined) {
      delete process.env[key];
    } else {
      process.env[key] = next;
    }
  }
  try {
    run();
  } finally {
    for (const key of Object.keys(vars)) {
      const prior = previous[key];
      if (prior === undefined) {
        delete process.env[key];
      } else {
        process.env[key] = prior;
      }
    }
  }
}

test("live build getDashboardMode is live", () => {
  withEnv({ NEXT_PUBLIC_DASHBOARD_MODE: "live", NEXT_PUBLIC_USE_MOCK_DATA: "false" }, () => {
    expect(getDashboardMode()).toBe("live");
    expect(useMockData()).toBe(false);
  });
});

test("production alias resolves as live operational mode", () => {
  withEnv({ NEXT_PUBLIC_DASHBOARD_MODE: "production", NEXT_PUBLIC_USE_MOCK_DATA: "false" }, () => {
    expect(getDashboardMode()).toBe("live");
    expect(resolveDataSourceMode()).toBe("laravelLive");
  });
});

test("live operational data source is laravelLive not laravelReadOnly", () => {
  withEnv(
    {
      NEXT_PUBLIC_DASHBOARD_MODE: "live",
      NEXT_PUBLIC_USE_MOCK_DATA: "false",
      NEXT_PUBLIC_DATA_SOURCE_UNAVAILABLE: "false",
    },
    () => {
      expect(resolveDataSourceMode()).toBe("laravelLive");
      expect(mapModeToLabel("laravelLive")).toBe("Live Laravel data");
      expect(mapModeToLabel("laravelReadOnly")).toBe("Read-only operational view");
      expect(mapModeToLabel("laravelReadOnly")).not.toContain("Mutations are disabled");
    },
  );
});

test("fixture mode remains available for development/tests", () => {
  withEnv({ NEXT_PUBLIC_DASHBOARD_MODE: "preview", NEXT_PUBLIC_USE_MOCK_DATA: "true" }, () => {
    expect(getDashboardMode()).toBe("preview");
    expect(resolveDataSourceMode()).toBe("fixture");
    expect(emptyListDescription(false)).toContain("Preview mode");
  });
});

test("live mutations are allowed unless explicitly disabled", () => {
  withEnv(
    {
      NEXT_PUBLIC_DASHBOARD_MODE: "live",
      NEXT_PUBLIC_ALLOW_MUTATIONS: undefined,
    },
    () => {
      expect(mutationsAllowed()).toBe(true);
    },
  );
  withEnv(
    {
      NEXT_PUBLIC_DASHBOARD_MODE: "live",
      NEXT_PUBLIC_ALLOW_MUTATIONS: "true",
    },
    () => {
      expect(mutationsAllowed()).toBe(true);
    },
  );
});

test("empty list copy never claims synthetic preview data on live", () => {
  expect(emptyListDescription(true)).not.toMatch(/synthetic preview/i);
  expect(emptyListDescription(true)).toContain("live JetPakistan backend");
});

test("intentional read-only label has no transition-phase wording", () => {
  const label = mapModeToLabel("laravelReadOnly");
  expect(label).toBe("Read-only operational view");
  expect(label.toLowerCase()).not.toContain("this phase");
  expect(label.toLowerCase()).not.toContain("laravel read-only");
});

test("live mode contract forbids preview.user fallback semantics", () => {
  withEnv(
    {
      NEXT_PUBLIC_DASHBOARD_MODE: "live",
      NEXT_PUBLIC_USE_MOCK_DATA: "false",
    },
    () => {
      expect(getDashboardMode()).toBe("live");
      expect(resolveDataSourceMode()).toBe("laravelLive");
      // Profile/header must fail closed — never invent preview.user from mode helpers.
      expect(emptyListDescription(true).toLowerCase()).not.toContain("preview.user");
      expect(emptyListDescription(true).toLowerCase()).not.toContain("synthetic");
    },
  );
});
