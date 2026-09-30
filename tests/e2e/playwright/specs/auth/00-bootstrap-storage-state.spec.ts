import { test, expect } from "@playwright/test";
import fs from "node:fs";
import path from "node:path";
import { loginAsQaRole } from "../../helpers/qa-login";
import type { QaRole } from "../../helpers/qa-credentials";

const roles: QaRole[] = ["admin", "staff", "agent", "customer"];
const authDir = path.join(process.cwd(), "tmp", "e2e-auth");

test.describe.configure({ mode: "serial" });

for (const role of roles) {
  test(`real login creates storage state for ${role}`, async ({ browser }) => {
    fs.mkdirSync(authDir, { recursive: true });
    const context = await browser.newContext({ storageState: undefined });
    const page = await context.newPage();
    await loginAsQaRole(page, role);
    await expect(page).not.toHaveURL(/\/login/);
    const out = path.join(authDir, `${role}.json`);
    await context.storageState({ path: out });
    await context.close();
    expect(fs.existsSync(out)).toBeTruthy();
  });
}
