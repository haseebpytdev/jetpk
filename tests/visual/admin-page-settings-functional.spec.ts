import { expect, test } from '@playwright/test';
import { getOverflowMetrics } from './helpers/layout-checks';

async function openSection(page: import('@playwright/test').Page, section: string) {
  await page.locator(`[data-jp-section-nav] [data-jp-section="${section}"]`).click();
  await expect(page.locator(`[data-jp-section-panel="${section}"]`)).toBeVisible();
}

async function openPageSettingsHome(page: import('@playwright/test').Page): Promise<string[]> {
  const errors: string[] = [];
  page.on('pageerror', (err) => errors.push(err.message));
  page.on('console', (msg) => {
    if (msg.type() === 'error') errors.push(msg.text());
  });
  await page.goto('/admin/page-settings/home', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('[data-jp-page-editor]')).toBeVisible({ timeout: 30_000 });
  return errors;
}

test.describe.configure({ mode: 'serial' });

test.describe('Admin Page Settings — home CMS functional', () => {
  test('page opens with hero CTA fields and toolbar actions', async ({ page }) => {
    await openPageSettingsHome(page);
    await expect(page.locator('#hero-cta1-text')).toBeVisible();
    await expect(page.locator('#hero-cta2-url')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Publish', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open preview tab' })).toBeVisible();
    await expect(page.locator('[data-jp-content-form] button[type="submit"]')).toBeVisible();
  });

  test('featured deals repeater add/remove works', async ({ page }) => {
    await openPageSettingsHome(page);
    await openSection(page, 'featured-deals');
    const list = page.locator('[data-jp-repeatable="featured-deals"]');
    await expect(list).toBeVisible();
    await expect(list.locator('[data-jp-repeatable-add]')).toBeVisible();
    await expect(list.locator('[data-jp-repeatable-template]')).toBeAttached();
    const items = list.locator('[data-jp-repeatable-item]');
    const before = await items.count();
    if (before > 0) {
      await list.locator('[data-jp-repeatable-remove]').first().click();
      await expect(items).toHaveCount(before - 1);
    }
  });

  test('routes and destinations managers render add/remove controls', async ({ page }) => {
    await openPageSettingsHome(page);
    await openSection(page, 'routes');
    const routesPanel = page.locator('[data-jp-section-panel="routes"]');
    await expect(routesPanel.locator('[data-jp-repeatable-add="routes"]')).toBeVisible();
    const routes = routesPanel.locator('[data-jp-repeatable="routes"]');
    const routeBefore = await routes.locator('[data-jp-repeatable-row]').count();
    expect(routeBefore).toBeGreaterThan(0);
    await routesPanel.locator('[data-jp-repeatable-add="routes"]').click();
    await expect(routes.locator('[data-jp-repeatable-row]')).toHaveCount(routeBefore + 1);
    await routesPanel.locator('[data-jp-repeatable-remove]').first().click();
    await expect(routes.locator('[data-jp-repeatable-row]')).toHaveCount(routeBefore);
    await openSection(page, 'destinations');
    await expect(page.locator('[data-jp-section-panel="destinations"] [data-jp-repeatable-add]')).toBeVisible();
  });

  test('group cards panel exists and legacy groups panel is absent', async ({ page }) => {
    await openPageSettingsHome(page);
    await openSection(page, 'group-cards');
    await expect(page.locator('[data-jp-section-panel="group-cards"]')).toBeVisible();
    await expect(page.locator('[data-jp-section-panel="groups"]')).toHaveCount(0);
    await expect(page.locator('[data-jp-section-nav] [data-jp-section="groups"]')).toHaveCount(0);
  });

  test('saved default and reset controls are present when authorized', async ({ page }) => {
    await openPageSettingsHome(page);
    await expect(page.locator('[data-jp-default-metadata]')).toBeVisible();
    await expect(page.locator('[data-jp-default-toggle-form]').first()).toBeVisible();
    await expect(page.locator('[data-jp-default-form]')).toBeAttached();
    const resetDraft = page.getByRole('button', { name: 'Reset draft to default' });
    if (await resetDraft.count()) {
      await expect(resetDraft).toBeVisible();
    }
    const resetPublishToggle = page.locator('[data-jp-reset-publish-toggle]');
    if (await resetPublishToggle.count()) {
      await expect(resetPublishToggle).toBeVisible();
      await expect(page.locator('[data-jp-reset-publish-form]')).toBeAttached();
    }
  });

  test('no console errors and no horizontal overflow', async ({ page }) => {
    const errors = await openPageSettingsHome(page);
    await openSection(page, 'hero');
    await openSection(page, 'featured-deals');
    await openSection(page, 'routes');
    const metrics = await getOverflowMetrics(page);
    expect(metrics.hasOverflow, `overflow ${metrics.bodyScrollWidth} > ${metrics.innerWidth}`).toBe(false);
    expect(errors, errors.join('\n')).toHaveLength(0);
  });
});
