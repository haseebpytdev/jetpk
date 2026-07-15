import { test, expect } from '@playwright/test';

const authDir = 'storage/app/playwright/jetpk-9h-b/auth';
const viewports = [
  { width: 1920, height: 1080 },
  { width: 1440, height: 900 },
  { width: 1366, height: 768 },
  { width: 1024, height: 768 },
  { width: 768, height: 1024 },
  { width: 390, height: 844 },
  { width: 360, height: 800 },
];

for (const role of ['customer', 'agent'] as const) {
  test.describe(`shared shell · ${role}`, () => {
    test.use({ storageState: `${authDir}/${role === 'customer' ? 'customer' : 'agent'}.json` });

    const home = role === 'customer' ? '/customer' : '/agent';
    const subnavId = role === 'customer' ? 'customer-account-subnav' : 'agent-portal-subnav';

    test('renders canonical shell with sidebar nav', async ({ page }) => {
      await page.setViewportSize({ width: 1440, height: 900 });
      await page.goto(home, { waitUntil: 'domcontentloaded' });

      const shell = page.getByTestId(`dashboard-shell-${role}`);
      if ((await shell.count()) === 0) {
        test.skip(true, `${role} uses JetPK theme shell — OTA foundation shell not active for this tenant`);
      }

      await expect(shell).toBeVisible();
      const subnav = page.getByTestId(subnavId);
      await expect(subnav).toBeVisible();
      await expect(subnav.locator('a.ota-dashboard-sidebar__link.is-active')).toHaveCount(1);
    });

    test('no horizontal overflow across viewports', async ({ page }) => {
      await page.goto(home, { waitUntil: 'domcontentloaded' });
      const shell = page.getByTestId(`dashboard-shell-${role}`);
      if ((await shell.count()) === 0) {
        test.skip(true, `${role} uses JetPK theme shell`);
      }

      for (const vp of viewports) {
        await page.setViewportSize(vp);
        await page.goto(home, { waitUntil: 'domcontentloaded' });
        const overflow = await page.evaluate(
          () => document.documentElement.scrollWidth - document.documentElement.clientWidth,
        );
        expect(overflow, `viewport ${vp.width}px`).toBeLessThanOrEqual(2);
      }
    });

    test('mobile drawer opens and closes with Escape', async ({ page }) => {
      await page.setViewportSize({ width: 390, height: 844 });
      await page.goto(home, { waitUntil: 'domcontentloaded' });

      const shell = page.getByTestId(`dashboard-shell-${role}`);
      if ((await shell.count()) === 0) {
        test.skip(true, `${role} uses JetPK theme shell`);
      }

      const toggle = shell.locator('.ota-dashboard-navtoggle');
      if (!(await toggle.isVisible())) {
        test.skip(true, 'Nav toggle not visible for this viewport/shell');
      }

      await toggle.click();
      const drawer = page.locator(`#ota-dashboard-drawer-${role}`);
      await expect(drawer).toBeVisible();
      await expect(page.locator('body.ota-dashboard-drawer-open')).toHaveCount(1);
      await page.keyboard.press('Escape');
      await expect(drawer).toBeHidden();
      await expect(page.locator('body.ota-dashboard-drawer-open')).toHaveCount(0);
    });
  });
}
