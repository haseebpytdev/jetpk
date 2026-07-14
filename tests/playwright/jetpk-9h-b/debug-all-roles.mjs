import { chromium } from '@playwright/test';

const baseURL = 'http://127.0.0.1:8000';
const roles = ['admin@ota.demo', 'staff@ota.demo', 'agent@ota.demo', 'agent.staff@demo.ota', 'customer@ota.demo'];

for (const email of roles) {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.goto(`${baseURL}/login`);
  await page.locator('form.jp-auth-form input[name="login"]').fill(email);
  await page.locator('form.jp-auth-form input[name="password"]').fill('password');
  await page.locator('form.jp-auth-form button[type="submit"]').click();
  await page.waitForTimeout(3000);
  if (!page.url().includes('/login/otp')) {
    console.log(email, 'FAILED login step =>', page.url(), await page.locator('body').innerText());
    await browser.close();
    continue;
  }
  await page.locator('input[name="otp"]').fill('123456');
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForTimeout(4000);
  console.log(email, '=>', page.url());
  await browser.close();
}
