import fs from "node:fs";
import { createRequire } from "node:module";
import { FINAL11_ROLE_KEYS, storagePathForRole } from "./final-11-auth-roles.mjs";
import { probeAllRoleSessions } from "./auth-session-probe.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

async function main() {
  /** @type {Record<string, string>} */
  const storagePaths = {};
  for (const roleKey of FINAL11_ROLE_KEYS) {
    storagePaths[roleKey] = storagePathForRole(roleKey);
    if (!fs.existsSync(storagePaths[roleKey])) {
      console.log(`${roleKey}=MISSING_STORAGE`);
    }
  }

  const browser = await chromium.launch({ headless: true });
  let runtimeErrors = 0;
  try {
    const { results, pass, fail, expected } = await probeAllRoleSessions(browser, storagePaths);
    for (const roleKey of FINAL11_ROLE_KEYS) {
      console.log(`${roleKey}=${results[roleKey] ?? "MISSING"}`);
    }
    console.log(`AUTH_STATES_EXPECTED=${expected}`);
    console.log(`AUTH_STATES_PASS=${pass}`);
    console.log(`AUTH_STATES_FAIL=${fail}`);
    console.log(`AUTH_PROBE_ROLE_AWARE=YES`);
    console.log(`AUTH_PROBE_RUNTIME_ERRORS=${runtimeErrors}`);
    process.exit(fail > 0 ? 1 : 0);
  } catch {
    runtimeErrors = 1;
    console.log(`AUTH_PROBE_RUNTIME_ERRORS=${runtimeErrors}`);
    process.exit(1);
  } finally {
    await browser.close();
  }
}

main();
