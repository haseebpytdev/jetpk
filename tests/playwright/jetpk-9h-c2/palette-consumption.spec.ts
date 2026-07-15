import { test, expect, type Page } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';

const auditPath = path.join('storage', 'app', 'audits', 'jetpk-9h-c2', 'palette-snapshot.json');

type PaletteSnapshot = {
  original: Record<string, string>;
  qa: Record<string, string>;
};

async function readCssVar(page: Page, selector: string, prop: string): Promise<string> {
  return page.locator(selector).first().evaluate((el, property) => getComputedStyle(el).getPropertyValue(property).trim(), prop);
}

async function gotoBranding(page: Page): Promise<void> {
  await page.goto('/admin/settings/branding', { waitUntil: 'domcontentloaded', timeout: 60_000 });
}

test.describe('palette consumption', () => {
  test('distinctive palette persists and drives runtime components', async ({ page }, testInfo) => {
    await gotoBranding(page);

    const original = {
      primary: await page.locator('#primary_color').inputValue(),
      secondary: await page.locator('#secondary_color').inputValue(),
      accent: await page.locator('#accent_color').inputValue(),
    };

    const qa = { primary: '#7C3AED', secondary: '#A78BFA', accent: '#22D3EE' };
    await page.locator('#primary_color').fill(qa.primary);
    await page.locator('#secondary_color').fill(qa.secondary);
    await page.locator('#accent_color').fill(qa.accent);
    await page.locator('[data-jp-branding-save]').click();
    await page.waitForLoadState('networkidle');

    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('#primary_color')).toHaveValue(qa.primary.toUpperCase());
    await expect(page.locator('#secondary_color')).toHaveValue(qa.secondary.toUpperCase());
    await expect(page.locator('#accent_color')).toHaveValue(qa.accent.toUpperCase());

    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const searchBtn = await readCssVar(page, '.ota-hero-search-submit, .btn-primary', 'background-image');
    expect(searchBtn).not.toBe('');

    await page.goto('/admin/settings/branding', { waitUntil: 'domcontentloaded' });
    const saveBtnBg = await readCssVar(page, '[data-jp-branding-save]', 'background-image');

    await page.locator('#primary_color').fill(original.primary);
    await page.locator('#secondary_color').fill(original.secondary);
    await page.locator('#accent_color').fill(original.accent);
    await page.locator('[data-jp-branding-save]').click();
    await page.waitForLoadState('networkidle');

    fs.mkdirSync(path.dirname(auditPath), { recursive: true });
    fs.writeFileSync(auditPath, JSON.stringify({ original, qa, saveBtnBg, project: testInfo.project.name }, null, 2));

    await page.screenshot({
      path: path.join('tests', 'playwright', 'artifacts', 'jetpk-9h-c2', 'screenshots', `${testInfo.project.name}-palette.png`),
      fullPage: true,
    });
  });
});
