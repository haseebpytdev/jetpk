import { createRequire } from "node:module";
import { loadQaPasswordFromVault } from "../jp-dash-03-acceptance/credential-vault.mjs";
import { fetchProductionOtp, productionLogin } from "./login-helpers.mjs";

const require = createRequire(import.meta.url);
const { chromium } = require("playwright");

async function main() {
  const otp = fetchProductionOtp();
  const pw = loadQaPasswordFromVault("admin");
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await productionLogin(page, "jp-dash-03-qa-admin@jetpakistan.pk", pw, otp);
  console.log("FINAL_URL=" + page.url().split("?")[0]);
  await browser.close();
}

main().catch((e) => {
  console.error(e.message);
  process.exit(1);
});
