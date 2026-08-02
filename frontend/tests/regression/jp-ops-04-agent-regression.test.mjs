import test from "node:test";
import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import path from "node:path";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const apiSource = readFileSync(path.join(root, "features/agent-dashboard/services/agent-dashboard-api.ts"), "utf8");
const shellSource = readFileSync(path.join(root, "features/agent-dashboard/shell/AgentDashboardShell.tsx"), "utf8");

test("agent dashboard API uses laravelRequest bridge", () => {
  assert.match(apiSource, /from "@\/lib\/api\/laravel-action-client"/);
  assert.match(apiSource, /laravelRequest/);
  assert.doesNotMatch(apiSource, /fetchAgentJson/);
  assert.match(apiSource, /retryCsrfOnce: false/);
});

test("agent dashboard API exposes staff reports commissions agency endpoints", () => {
  assert.match(apiSource, /fetchAgentStaffList/);
  assert.match(apiSource, /fetchAgentReports/);
  assert.match(apiSource, /fetchAgentCommissions/);
  assert.match(apiSource, /fetchAgentAgency/);
  assert.match(apiSource, /fetchAgentBookingCreateEntry/);
  assert.match(apiSource, /requestAgentBookingCancellation/);
});

test("agent shell does not hardcode fallback navigation grants", () => {
  assert.doesNotMatch(shellSource, /label: "Bookings", href: "\/agent\/bookings", available: true/);
  assert.match(shellSource, /agent-capabilities-loading/);
  assert.match(shellSource, /capabilities\?\.navigation \?\? \[\]/);
});
