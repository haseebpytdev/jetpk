/**
 * Local Playwright smoke: customer + agent dashboards after BOM hotfix.
 */
import { createRequire } from "module";
import { spawnSync } from "child_process";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";

const require = createRequire(import.meta.url);
const { chromium } = require("../../../frontend/node_modules/playwright");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const sshKey = process.env.JP_SSH_KEY || `${process.env.USERPROFILE}\\.ssh\\jetpk_contabo_2026_v2`;
const outDir = path.join(__dirname, "live", "c08-dashboard-smoke");

function ssh(cmd) {
  const r = spawnSync("ssh", ["-i", sshKey, "-o", "BatchMode=yes", "root@185.215.166.176", cmd], {
    encoding: "utf8",
    maxBuffer: 5_000_000,
  });
  if (r.status !== 0) throw new Error(r.stderr || r.stdout);
  return r.stdout;
}

function mint(userId) {
  const out = ssh(`bash /tmp/jp-c07c-mint-admin-session.sh ${userId}`);
  return {
    name: (out.match(/SESSION_COOKIE_NAME=(.+)/) || [])[1]?.trim(),
    value: (out.match(/SESSION_COOKIE_VALUE=(.+)/) || [])[1]?.trim(),
    email: (out.match(/AUTH_EMAIL=(.+)/) || [])[1]?.trim(),
  };
}

async function checkDashboard(page, label, url, opts) {
  const failed = [];
  const consoleErrors = [];
  page.on("console", (m) => {
    if (m.type() === "error") consoleErrors.push(m.text());
  });
  page.on("response", (res) => {
    if (res.url().includes("/laravel/") && res.status() >= 400) {
      failed.push(`${res.status()} ${res.url()}`);
    }
  });
  await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60_000 });
  await page.waitForTimeout(opts.waitMs ?? 10_000);
  const body = await page.locator("body").innerText();
  const overviewSel = opts.overviewTestId;
  const overview = overviewSel ? await page.locator(`[data-testid="${overviewSel}"]`).count() : 0;
  const loading = /Loading overview|Loading navigation/i.test(body);
  const something = /Something went wrong/i.test(body);
  const unable = /Unable to load data|OV-UNKNOWN/i.test(body);
  const positive = opts.positive?.some((re) => re.test(body)) ?? false;
  console.log(`=== ${label} ${page.viewportSize()?.width} ===`);
  console.log("URL", page.url());
  console.log("OVERVIEW", overview);
  console.log("LOADING", loading ? "YES" : "NO");
  console.log("SOMETHING_WRONG", something ? "YES" : "NO");
  console.log("UNABLE", unable ? "YES" : "NO");
  console.log("POSITIVE", positive ? "YES" : "NO");
  console.log("LARAVEL_FAILS", failed.length);
  failed.slice(0, 8).forEach((f) => console.log("NET", f));
  consoleErrors.slice(0, 5).forEach((e) => console.log("ERR", e.slice(0, 180)));
  fs.mkdirSync(outDir, { recursive: true });
  const file = path.join(outDir, `${label}-${page.viewportSize()?.width}.png`);
  await page.screenshot({ path: file, fullPage: true });
  console.log("SHOT", file);
  return { loading, something, unable, overview, positive, failed };
}

async function main() {
  const customer = mint(11);
  const agent = mint(10);
  const admin = mint(9);
  console.log("minted", customer.email, agent.email, admin.email);

  const browser = await chromium.launch({ headless: true });

  async function withCookie(cookie, viewport, fn) {
    const context = await browser.newContext({ viewport });
    await context.addCookies([
      {
        name: cookie.name,
        value: cookie.value,
        domain: "jetpakistan.pk",
        path: "/",
        httpOnly: true,
        secure: true,
        sameSite: "Lax",
      },
    ]);
    const page = await context.newPage();
    try {
      return await fn(page);
    } finally {
      await context.close();
    }
  }

  const cust1440 = await withCookie(customer, { width: 1440, height: 900 }, (page) =>
    checkDashboard(page, "customer", "https://jetpakistan.pk/customer/dashboard", {
      overviewTestId: "customer-dashboard-overview",
      positive: [/Upcoming trips|Pending payment|Recent bookings|No bookings yet|Dashboard overview/i],
    }),
  );
  const cust390 = await withCookie(customer, { width: 390, height: 844 }, (page) =>
    checkDashboard(page, "customer", "https://jetpakistan.pk/customer/dashboard", {
      overviewTestId: "customer-dashboard-overview",
      positive: [/Upcoming trips|Pending payment|Recent bookings|No bookings yet|Dashboard overview/i],
    }),
  );

  const agent1440 = await withCookie(agent, { width: 1440, height: 900 }, (page) =>
    checkDashboard(page, "agent", "https://jetpakistan.pk/agent/dashboard", {
      overviewTestId: "agent-dashboard-overview",
      positive: [/Agent|Overview|Bookings|Dashboard/i],
      waitMs: 12_000,
    }),
  );
  const agent390 = await withCookie(agent, { width: 390, height: 844 }, (page) =>
    checkDashboard(page, "agent", "https://jetpakistan.pk/agent/dashboard", {
      overviewTestId: "agent-dashboard-overview",
      positive: [/Agent|Overview|Bookings|Dashboard/i],
      waitMs: 12_000,
    }),
  );

  const admin1440 = await withCookie(admin, { width: 1440, height: 900 }, (page) =>
    checkDashboard(page, "admin", "https://jetpakistan.pk/admin", {
      positive: [/Overview|Dashboard|Bookings|Revenue/i],
      waitMs: 15_000,
    }),
  );

  await browser.close();

  const summary = {
    CUSTOMER_DASHBOARD:
      !cust1440.loading &&
      !cust390.loading &&
      !cust1440.something &&
      !cust390.something &&
      cust1440.overview > 0 &&
      cust390.overview > 0
        ? "PASS"
        : "FAIL",
    AGENT_DASHBOARD:
      !agent1440.loading && !agent390.loading && !agent1440.something && !agent390.something
        ? "PASS"
        : "FAIL",
    ADMIN_DASHBOARD:
      !admin1440.unable && !admin1440.something && admin1440.positive ? "PASS" : "FAIL",
  };
  console.log(JSON.stringify(summary, null, 2));
  fs.writeFileSync(path.join(outDir, "summary.json"), JSON.stringify({ summary, cust1440, cust390, agent1440, agent390, admin1440 }, null, 2));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
