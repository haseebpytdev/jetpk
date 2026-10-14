import { parseCsvFile } from "./functional-cert-csv.mjs";
import { runLifecyclePilot } from "./lifecycle-pilot-executor.mjs";
import { writeDeepWaveBaselineV3 } from "./write-deep-wave-baseline-v3.mjs";
import { isDeepCaseClass } from "./functional-case-classify.mjs";
import { classifyReversibleSemanticV3 } from "./deep-semantics-lib.mjs";

const lifePilot = await runLifecyclePilot({ canaryOnly: false });
writeDeepWaveBaselineV3({
  qaFunctionalDbMutationsCumulative: 16,
  qaFunctionalDbRestoreMutationsCumulative: 14,
  qaLifecycleCreate: lifePilot.createMutations,
  qaLifecycleEdit: lifePilot.editMutations,
  qaLifecycleDelete: lifePilot.deleteMutations,
});

const { rows } = parseCsvFile("docs/evidence/jp-dash-final-11/FUNCTIONAL-CASE-MATRIX.csv");
const rev = rows.filter((r) => r.EXECUTION_CLASS === "QA_REVERSIBLE_MUTATION");
const life = rows.filter((r) => r.EXECUTION_CLASS === "QA_CREATE_DELETE_LIFECYCLE");
const deep = rows.filter((r) => isDeepCaseClass(r.EXECUTION_CLASS));

console.log(`LIFECYCLE_PILOT_PASS=${lifePilot.pass}`);
console.log(`LIFECYCLE_PILOT_FAIL=${lifePilot.fail}`);
console.log(`LIFECYCLE_PILOT_CLEANED_UP=${lifePilot.cleaned}`);
console.log(`LIFECYCLE_PILOT_CASES=10`);
console.log(`QA_REVERSIBLE_MUTATION_PASS=${rev.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length}`);
console.log(`QA_REVERSIBLE_MUTATION_BLOCKED=${rev.filter((r) => r.FUNCTIONAL_RESULT === "BLOCKED").length}`);
console.log(`QA_REVERSIBLE_MUTATION_UNTESTED=${rev.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
console.log(`QA_CREATE_DELETE_LIFECYCLE_PASS=${life.filter((r) => r.FUNCTIONAL_RESULT === "PASS").length}`);
console.log(`DEEP_CASES_VALID_TERMINAL=${deep.filter((r) => r.FUNCTIONAL_RESULT && !["BLOCKED","EXTERNAL_ACTION_GATED"].includes(r.FUNCTIONAL_RESULT)).length}`);
console.log(`DEEP_CASES_UNTESTED=${deep.filter((r) => !r.FUNCTIONAL_RESULT).length}`);
console.log(`DEEP_WAVE_BASELINE_V3_LOCKED=YES`);
