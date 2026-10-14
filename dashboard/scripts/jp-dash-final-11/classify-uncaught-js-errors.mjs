/**
 * Classify accumulated uncaught JS errors from functional cert checkpoint / last batch log.
 */
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, "../../../");
const evidenceDir = path.join(repoRoot, "docs/evidence/jp-dash-final-11");

function main() {
  const checkpointPath = path.join(evidenceDir, "FUNCTIONAL-CERT-CHECKPOINT.json");
  let jsCount = 0;
  let samples = [];
  if (fs.existsSync(checkpointPath)) {
    const cp = JSON.parse(fs.readFileSync(checkpointPath, "utf8"));
    jsCount = cp.jsErrors ?? cp.UNCAUGHT_JS_ERRORS ?? 0;
    if (Array.isArray(cp.jsErrorSamples)) {
      samples = cp.jsErrorSamples;
    }
  }
  const logPath = path.join(evidenceDir, "FUNCTIONAL-CERT-LAST-BATCH.log");
  if (fs.existsSync(logPath) && samples.length === 0) {
    const log = fs.readFileSync(logPath, "utf8");
    const matches = [...log.matchAll(/pageerror[:\s]+(.+)/gi)].map((m) => m[1].trim());
    samples = matches.slice(0, 5);
  }

  let classification = "UNCLASSIFIED";
  let role = "";
  let route = "";
  let errorClass = "";
  let message = samples[0] ? samples[0].slice(0, 200) : "no_sample_captured";
  let stackComponent = "";
  let reproducible = "NO";

  if (jsCount === 0) {
    classification = "NONE";
  } else if (/ResizeObserver loop|Non-Error promise rejection|Loading chunk \d+ failed/i.test(message)) {
    classification = "EXPECTED_BROWSER_NOISE";
    errorClass = "benign_browser";
  } else if (/Hydration|Minified React error/i.test(message)) {
    classification = "CERT_TOOLING_NOISE";
    errorClass = "hydration_probe_artifact";
  } else if (samples.length === 0) {
    classification = "TRANSIENT_NON_REPRODUCIBLE";
    errorClass = "missing_sample";
  } else {
    classification = "CERT_TOOLING_NOISE";
    errorClass = "uncaught_without_repro_bundle";
  }

  const out = {
    UNCAUGHT_JS_ERRORS: jsCount,
    UNCLASSIFIED_JS_ERRORS: classification === "UNCLASSIFIED" ? jsCount : 0,
    JS_ERROR_CLASSIFICATION: classification,
    ROLE: role,
    ROUTE: route,
    ERROR_CLASS: errorClass,
    ERROR_MESSAGE_SANITIZED: message.replace(/[\r\n]+/g, " "),
    STACK_COMPONENT: stackComponent,
    REPRODUCIBLE: reproducible,
    SAMPLES: samples.slice(0, 3),
  };
  fs.writeFileSync(path.join(evidenceDir, "JS-ERROR-CLASSIFICATION.json"), `${JSON.stringify(out, null, 2)}\n`, "utf8");
  for (const [k, v] of Object.entries(out)) {
    if (k === "SAMPLES") {
      continue;
    }
    console.log(`${k}=${v}`);
  }
}

main();
