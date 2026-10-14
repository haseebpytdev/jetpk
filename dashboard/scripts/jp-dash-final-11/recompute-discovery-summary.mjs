import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const repoRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../../");
const evidence = path.join(repoRoot, "docs/evidence/jp-dash-final-11");
const inventoryPath = path.join(evidence, "ROUTE-ACTION-INVENTORY.csv");
const summaryPath = path.join(evidence, "discovery-summary.json");

const lines = fs.readFileSync(inventoryPath, "utf8").split(/\r?\n/).filter(Boolean);
const dataRows = lines.slice(1);
const functionallyTested = dataRows.filter(
  (line) => line.includes(",FUNCTIONAL_PROBE,") || line.includes(",MUTATION_VERIFIED,"),
).length;

const summary = JSON.parse(fs.readFileSync(summaryPath, "utf8"));
summary.totalControlsEnumerated = dataRows.length;
summary.totalControlsFunctionallyTested = functionallyTested;
summary.untestedControls = Math.max(0, summary.totalControlsEnumerated - summary.totalControlsFunctionallyTested);
summary.controlsAccountingCorrectedAt = new Date().toISOString();
summary.controlsAccountingNote =
  "Enumerated controls use VISUAL_ENUMERATION/LIVE_BROWSER_GET; functional probes are not implied by PASS rows.";

fs.writeFileSync(summaryPath, `${JSON.stringify(summary, null, 2)}\n`, "utf8");
console.log(`TOTAL_CONTROLS_ENUMERATED=${summary.totalControlsEnumerated}`);
console.log(`TOTAL_CONTROLS_FUNCTIONALLY_TESTED=${summary.totalControlsFunctionallyTested}`);
console.log(`UNTESTED_CONTROLS=${summary.untestedControls}`);
