/**
 * JP-FULLSTACK-01A-R1 force-password fixture clearance security regression.
 */

import {
  applyForcePasswordFixtureClearancePolicy,
  FORCE_PASSWORD_CLEARANCE_COOKIE,
  hasForcePasswordClearanceCookie,
  readSessionFixtureValueFromCookieHeader,
  shouldHonorForcePasswordClearanceCookie,
  shouldWriteForcePasswordClearanceCookie,
} from "../../features/auth/utils/force-password-clearance-policy.mjs";

let failed = 0;

function assert(condition, message) {
  if (!condition) {
    console.error(`FAIL: ${message}`);
    failed += 1;
  }
}

const forcePasswordBootstrap = {
  authenticated: true,
  requires_password_change: true,
  user: { account_type: "customer" },
};

const clearanceCookies = [{ name: FORCE_PASSWORD_CLEARANCE_COOKIE, value: "1" }];

// Write gate: only force-password fixture values
assert(!shouldWriteForcePasswordClearanceCookie(null), "write: null fixture blocked");
assert(!shouldWriteForcePasswordClearanceCookie("customer"), "write: customer fixture blocked");
assert(!shouldWriteForcePasswordClearanceCookie("agent"), "write: agent fixture blocked");
assert(
  shouldWriteForcePasswordClearanceCookie("customer_force_password"),
  "write: customer_force_password allowed",
);
assert(
  shouldWriteForcePasswordClearanceCookie("agent_force_password"),
  "write: agent_force_password allowed",
);

// Read gate: fixture mode + force-password fixture + clearance cookie
assert(
  !shouldHonorForcePasswordClearanceCookie(false, "customer_force_password", true),
  "read: disabled outside fixture mode",
);
assert(
  !shouldHonorForcePasswordClearanceCookie(true, "customer", true),
  "read: non-force fixture ignored",
);
assert(
  !shouldHonorForcePasswordClearanceCookie(true, "customer_force_password", false),
  "read: missing clearance ignored",
);
assert(
  shouldHonorForcePasswordClearanceCookie(true, "customer_force_password", true),
  "read: fixture mode honors clearance",
);

// Cookie carries no credentials
assert(
  FORCE_PASSWORD_CLEARANCE_COOKIE === "ota_force_password_cleared",
  "cookie name is non-credential marker",
);
assert(hasForcePasswordClearanceCookie(clearanceCookies), "clearance detection");
assert(
  !hasForcePasswordClearanceCookie([{ name: FORCE_PASSWORD_CLEARANCE_COOKIE, value: "token-secret" }]),
  "clearance requires literal value 1",
);

// Policy: outside fixture mode clearance cannot clear requirement
{
  const result = applyForcePasswordFixtureClearancePolicy(
    forcePasswordBootstrap,
    false,
    "customer_force_password",
    clearanceCookies,
  );
  assert(result.requires_password_change === true, "policy: production mode ignores clearance");
}

// Policy: fixture mode honors clearance for force-password fixtures only
{
  const cleared = applyForcePasswordFixtureClearancePolicy(
    forcePasswordBootstrap,
    true,
    "customer_force_password",
    clearanceCookies,
  );
  assert(cleared.requires_password_change === false, "policy: fixture mode clears requirement");
}

{
  const unchanged = applyForcePasswordFixtureClearancePolicy(
    forcePasswordBootstrap,
    true,
    "customer",
    clearanceCookies,
  );
  assert(unchanged.requires_password_change === true, "policy: customer fixture ignores clearance");
}

// Failed mutation path: no clearance cookie → requirement remains
{
  const unchanged = applyForcePasswordFixtureClearancePolicy(
    forcePasswordBootstrap,
    true,
    "customer_force_password",
    [],
  );
  assert(unchanged.requires_password_change === true, "policy: no clearance keeps requirement");
}

// Cookie header parser
assert(
  readSessionFixtureValueFromCookieHeader("ota_session_fixture=customer_force_password") ===
    "customer_force_password",
  "fixture cookie parser",
);
assert(readSessionFixtureValueFromCookieHeader("other=1") === null, "fixture parser absent");

if (failed > 0) process.exit(1);
console.log("JP-FULLSTACK-01A-R1 force-password clearance security: all cases passed");
