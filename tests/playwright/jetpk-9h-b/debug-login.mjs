import { chromium } from '@playwright/test';
import fs from 'node:fs';

const baseURL = 'http://127.0.0.1:8000';
const browser = await chromium.launch();
const page = await browser.newPage();
await page.goto(`${baseURL}/login`);
await page.locator('form.jp-auth-form input[name="login"]').fill('admin@ota.demo');
await page.locator('form.jp-auth-form input[name="password"]').fill('password');
await page.locator('form.jp-auth-form button[type="submit"]').click();
await page.waitForTimeout(3000);
if (page.url().includes('/login/otp')) {
  await page.locator('input[name="otp"]').fill('123456');
  await page.locator('form button[type="submit"]').first().click();
  await page.waitForTimeout(3000);
}
fs.mkdirSync('storage/app/audits/jetpk-9h-b', { recursive: true });
fs.writeFileSync('storage/app/audits/jetpk-9h-b/debug-login.html', await page.content());
fs.writeFileSync('storage/app/audits/jetpk-9h-b/debug-login.txt', `url=${page.url()}\n\n${await page.locator('body').innerText()}`);
console.log('url=', page.url());
await browser.close();
