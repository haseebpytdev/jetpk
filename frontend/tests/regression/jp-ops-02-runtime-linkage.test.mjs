/**
 * Ensures production laravel-action-client imports the shared security policy modules.
 */

import { readFileSync } from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const frontendRoot = path.resolve(__dirname, "../..");
const clientPath = path.join(frontendRoot, "lib/api/laravel-action-client.ts");
const clientSource = readFileSync(clientPath, "utf8");

const requiredBindings = [
  {
    module: "csrf-retry-policy.mjs",
    symbols: ["shouldRetryAfterCsrfExpired", "pathAllowsCsrfAutoRetry"],
  },
  {
    module: "response-payload-policy.mjs",
    symbols: ["normalizeNonJsonPayload"],
  },
];

let failed = 0;

function fail(message) {
  console.error(`FAIL: ${message}`);
  failed += 1;
}

for (const binding of requiredBindings) {
  if (!clientSource.includes(binding.module)) {
    fail(`laravel-action-client.ts must import ${binding.module}`);
    continue;
  }

  for (const symbol of binding.symbols) {
    if (!clientSource.includes(symbol)) {
      fail(`laravel-action-client.ts must invoke ${symbol} from ${binding.module}`);
    }
  }
}

if (failed > 0) process.exit(1);
console.log("JP-OPS-02 runtime linkage: laravel-action-client uses shared security policies");
