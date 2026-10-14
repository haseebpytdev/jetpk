import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const evidence = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../../docs/evidence/jp-dash-final-11");
const plan = JSON.parse(fs.readFileSync(path.join(evidence, "FINAL-RESPONSIVE-EXECUTION-PLAN.json"), "utf8"));
const old = JSON.parse(fs.readFileSync(path.join(evidence, "FINAL-REACHABLE-ROUTES.json"), "utf8"));
const by = {};
for (const r of plan.routeInstances) {
  (by[r.roleKey] ||= []).push(r.route);
}
console.log(`NEW_TOTAL=${plan.ROUTES} PLAN_ROWS=${plan.PLAN_ROWS}`);
console.log(`OLD_TOTAL=${old.totalReachableRouteInstances}`);
const roles = [...new Set([...Object.keys(by), ...Object.keys(old.byRole || {})])].sort();
for (const role of roles) {
  const nr = [...new Set(by[role] || [])].sort();
  const raw = old.byRole?.[role]?.routes;
  const or = Array.isArray(raw)
    ? raw
    : String(raw || "")
        .split(/\s+/)
        .filter(Boolean);
  const os = new Set(or);
  const ns = new Set(nr);
  const added = [...ns].filter((x) => !os.has(x));
  const removed = [...os].filter((x) => !ns.has(x));
  console.log(
    `${role} old=${or.length} new=${nr.length} added=${JSON.stringify(added)} removed=${JSON.stringify(removed)}`,
  );
}
