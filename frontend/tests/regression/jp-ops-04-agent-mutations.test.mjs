/**
 * JP-OPS-04 durable mutation wiring regression — reads production agent-dashboard-api.ts.
 */

import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";
import test from "node:test";
import assert from "node:assert/strict";

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../..");
const apiPath = path.join(root, "features/agent-dashboard/services/agent-dashboard-api.ts");
const apiSource = readFileSync(apiPath, "utf8");
const cancelPanelPath = path.join(root, "features/agent-dashboard/bookings/AgentBookingCancellationPanel.tsx");
const cancelPanelSource = readFileSync(cancelPanelPath, "utf8");
const depositPagePath = path.join(root, "features/agent-dashboard/deposits/DepositListPage.tsx");
const depositPageSource = readFileSync(depositPagePath, "utf8");
const layoutPath = path.join(root, "app/agent/layout.tsx");
const layoutSource = readFileSync(layoutPath, "utf8");
const shellPath = path.join(root, "features/agent-dashboard/shell/AgentDashboardShell.tsx");
const shellSource = readFileSync(shellPath, "utf8");

test("requestAgentBookingCancellation posts to booking-reference endpoint", () => {
  assert.match(apiSource, /requestAgentBookingCancellation\(\s*bookingReference: string/);
  assert.match(apiSource, /\/agent\/bookings\/\$\{encodeURIComponent\(bookingReference\)\}\/cancellations\?format=json/);
});

test("submitAgentDeposit uses CSRF-protected agentMutation without auto replay", () => {
  assert.match(apiSource, /retryCsrfOnce: false/);
  assert.match(apiSource, /submitAgentDeposit\(formData: FormData\)/);
  assert.match(apiSource, /\/agent\/deposits\?format=json/);
});

test("staff mutations derive paths from staff id without agency_id", () => {
  assert.match(apiSource, /\/agent\/staff\/\$\{staffId\}/);
  assert.doesNotMatch(apiSource, /agency_id/);
  assert.match(apiSource, /createAgentStaff\(payload:/);
  assert.match(apiSource, /updateAgentStaff\(/);
});

test("fetchAgentBookingCreateEntry is search handoff only", () => {
  assert.match(apiSource, /fetchAgentBookingCreateEntry/);
  assert.match(apiSource, /\/agent\/bookings\/create\?format=json/);
  assert.doesNotMatch(apiSource, /\/agent\/bookings\?format=json.*method: "POST"/s);
});

test("cancellation panel submits via requestAgentBookingCancellation with bookingReference", () => {
  assert.match(cancelPanelSource, /requestAgentBookingCancellation\(bookingReference/);
  assert.match(cancelPanelSource, /submitting/);
});

test("deposit CTA is gated on can_submit_deposit capability", () => {
  assert.match(depositPageSource, /can_submit_deposit/);
  assert.match(depositPageSource, /data-testid="deposit-new-cta"/);
  assert.match(depositPageSource, /canCreateDeposit \?/);
});

test("agent layout authorizes before rendering portal shell", () => {
  assert.match(layoutSource, /requireAgentPortalLayoutAccess/);
  assert.doesNotMatch(layoutSource, /fetchAgentDashboardOverview|fetchAgentCapabilities/);
});

test("agent shell has no fallback navigation before capabilities load", () => {
  assert.match(shellSource, /agent-capabilities-loading/);
  assert.match(shellSource, /capabilities\?\.navigation \?\? \[\]/);
  assert.doesNotMatch(shellSource, /label: "Bookings", href: "\/agent\/bookings", available: true/);
});
