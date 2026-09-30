import { test, expect } from "@playwright/test";
import path from "node:path";

test("unauthenticated dashboard APIs are denied", async ({ browser }) => {
  const context = await browser.newContext({ storageState: undefined });
  const page = await context.newPage();
  await page.goto("/login", { waitUntil: "domcontentloaded" });
  const session = await page.request.get("/api/dashboard/session?portal=admin");
  expect(session.status() === 401 || session.status() === 403 || session.status() >= 400).toBeTruthy();

  const bookings = await page.request.get("/api/dashboard/bookings");
  expect(bookings.status()).toBeGreaterThanOrEqual(400);
  await context.close();
});

test("customer storage cannot call admin bookings list successfully", async ({ browser }) => {
  const context = await browser.newContext({
    storageState: path.join(process.cwd(), "tmp", "e2e-auth", "customer.json"),
  });
  const page = await context.newPage();
  await page.goto("/customer/bookings", { waitUntil: "domcontentloaded" });
  const result = await page.evaluate(async () => {
    const res = await fetch("/api/dashboard/bookings", {
      credentials: "same-origin",
      headers: { Accept: "application/json" },
    });
    return { status: res.status };
  });
  expect(result.status).toBeGreaterThanOrEqual(400);
  await context.close();
});

test("agent storage cannot open admin settings hub", async ({ browser }) => {
  const context = await browser.newContext({
    storageState: path.join(process.cwd(), "tmp", "e2e-auth", "agent.json"),
  });
  const page = await context.newPage();
  await page.goto("/admin/dashboard/settings/general", { waitUntil: "domcontentloaded" });
  const url = page.url();
  const body = await page.content();
  const denied =
    !/\/admin\/dashboard\/settings\/general/.test(url) ||
    body.includes("403") ||
    /login|\/agent|forbidden|unauthorized/i.test(url + (await page.title()));
  expect(denied).toBeTruthy();
  await context.close();
});
