/**
 * JP-OPS-05 runtime linkage regression — live mode gates and mutation client wiring.
 */

import { getDashboardMode, mutationsAllowed, useMockData } from "../../lib/preview.ts";

let failed = 0;

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    failed += 1;
  }
}

const originalMode = process.env.NEXT_PUBLIC_DASHBOARD_MODE;
const originalMock = process.env.NEXT_PUBLIC_USE_MOCK_DATA;
const originalMutations = process.env.NEXT_PUBLIC_ALLOW_MUTATIONS;

try {
  process.env.NEXT_PUBLIC_DASHBOARD_MODE = "live";
  process.env.NEXT_PUBLIC_USE_MOCK_DATA = "false";
  assert(getDashboardMode() === "live", "live mode resolves");
  assert(useMockData() === false, "live mode disables mock data");
  assert(mutationsAllowed() === true, "live mode allows mutations by default");

  process.env.NEXT_PUBLIC_DASHBOARD_MODE = "preview";
  delete process.env.NEXT_PUBLIC_USE_MOCK_DATA;
  assert(getDashboardMode() === "preview", "preview mode resolves");
  assert(useMockData() === true, "preview mode enables mock data by default");
} finally {
  if (originalMode === undefined) delete process.env.NEXT_PUBLIC_DASHBOARD_MODE;
  else process.env.NEXT_PUBLIC_DASHBOARD_MODE = originalMode;
  if (originalMock === undefined) delete process.env.NEXT_PUBLIC_USE_MOCK_DATA;
  else process.env.NEXT_PUBLIC_USE_MOCK_DATA = originalMock;
  if (originalMutations === undefined) delete process.env.NEXT_PUBLIC_ALLOW_MUTATIONS;
  else process.env.NEXT_PUBLIC_ALLOW_MUTATIONS = originalMutations;
}

console.log(`jp-ops-05-runtime-linkage: ${failed === 0 ? "PASS" : `${failed} FAIL`}`);
process.exit(failed > 0 ? 1 : 0);
