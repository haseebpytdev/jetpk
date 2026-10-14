import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../../");
const networkPath = path.join(repoRoot, "docs/evidence/jp-dash-final-11/BROWSER-CONSOLE-NETWORK.json");

const data = JSON.parse(fs.readFileSync(networkPath, "utf8"));
const hydrationErrors = (data.hydration ?? []).length;
const consoleErrors = (data.consoleErrors ?? []).reduce((acc, item) => {
  const errs = item.errors ?? [];
  return acc + errs.filter((e) => !/status of 403|status of 401|status of 404/i.test(String(e))).length;
}, 0);
const page500Errors = (data.consoleErrors ?? []).reduce((acc, item) => {
  const errs = item.errors ?? [];
  return acc + errs.filter((e) => /status of 500/i.test(String(e))).length;
}, 0);
const failedRequests = (data.failedRequests ?? []).reduce((acc, item) => acc + (item.failures?.length ?? 0), 0);

console.log(`HYDRATION_ERRORS=${hydrationErrors}`);
console.log(`UNCAUGHT_JS_ERRORS=${consoleErrors}`);
console.log(`PAGE_500_ERRORS=${page500Errors}`);
console.log(`UNEXPECTED_NETWORK_FAILURES=${failedRequests}`);
console.log(`BROKEN_ASSET_REQUESTS=${failedRequests}`);
