/**
 * JetPakistan results filter panel visual closure.
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { AUDIT_VIEWPORTS, futureDepartDate } from './helpers/constants';

const OUT = 'storage/app/audits/jetpk-filter-panel-visual';
const FILTER_PANEL = '.jp-filter-panel, .ota-filter-card[data-filter-drawer]';
const FILTER_SORT = '#ota-filter-sort';
const FILTER_LABEL = '.jp-filter-label';
const FILTER_TITLE = '.jp-filter-panel__title';
const FILTER_CHECK = '[data-filter-refundable].jp-filter-check';

function resultsPath(): string {
  const depart = futureDepartDate();
  return `/flights/results?trip_type=one_way&from=ISB&to=KHI&from_display=Islamabad&to_display=Karachi&depart=${depart}&adults=1&children=0&infants=0&cabin=economy`;
}

function parsePx(value: string): number {
  return Number.parseFloat(value.replace('px', ''));
}

function isBlueish(value: string): boolean {
  const v = value.toLowerCase();
  return v.includes('2563eb') || v.includes('37, 99, 235') || v.includes('59, 130, 246') || v.includes('93c5fd');
}

test.describe('JetPK filter panel visual closure', () => {
  test.beforeAll(() => {
    fs.mkdirSync(path.join(OUT, 'screenshots'), { recursive: true });
  });

  test('closed panel controls match JetPakistan design tokens', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${resultsPath()}&_fp=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector(FILTER_PANEL, { timeout: 60_000 });

    const styles = await page.evaluate(() => {
      const panel = document.querySelector('.jp-filter-panel, .ota-filter-card[data-filter-drawer]');
      const select = document.querySelector('#ota-filter-sort');
      const label = document.querySelector('.jp-filter-label');
      const title = document.querySelector('.jp-filter-panel__title');
      const checkbox = document.querySelector('[data-filter-refundable].jp-filter-check');
      const cs = (el: Element | null) => (el ? getComputedStyle(el) : null);
      const selectCs = cs(select);
      return {
        panelVisible: !!panel && (panel as HTMLElement).offsetParent !== null,
        select: selectCs
          ? {
              fontFamily: selectCs.fontFamily,
              fontSize: selectCs.fontSize,
              fontWeight: selectCs.fontWeight,
              height: selectCs.height,
              paddingRight: selectCs.paddingRight,
              borderRadius: selectCs.borderRadius,
              appearance: selectCs.appearance,
              boxShadow: selectCs.boxShadow,
              outline: selectCs.outline,
              backgroundImage: selectCs.backgroundImage,
            }
          : null,
        label: (() => {
          const l = cs(label);
          return l ? { fontFamily: l.fontFamily, fontSize: l.fontSize, fontWeight: l.fontWeight } : null;
        })(),
        title: (() => {
          const t = cs(title);
          return t ? { fontFamily: t.fontFamily, fontSize: t.fontSize, fontWeight: t.fontWeight, textTransform: t.textTransform } : null;
        })(),
        checkbox: checkbox
          ? {
              accentColor: getComputedStyle(checkbox).accentColor,
              width: getComputedStyle(checkbox).width,
              height: getComputedStyle(checkbox).height,
            }
          : null,
      };
    });

    expect(styles.panelVisible).toBe(true);
    expect(styles.select).not.toBeNull();
    expect(styles.select!.fontFamily.toLowerCase()).toContain('inter');
    expect(parsePx(styles.select!.fontSize)).toBeGreaterThanOrEqual(13);
    expect(parsePx(styles.select!.fontSize)).toBeLessThanOrEqual(16);
    expect(parsePx(styles.select!.height)).toBeGreaterThanOrEqual(42);
    expect(parsePx(styles.select!.height)).toBeLessThanOrEqual(48);
    expect(parsePx(styles.select!.paddingRight)).toBeGreaterThan(24);
    expect(styles.select!.appearance).toBe('none');
    expect(styles.select!.backgroundImage).not.toBe('none');
    expect(isBlueish(styles.select!.boxShadow)).toBe(false);
    expect(isBlueish(styles.select!.outline)).toBe(false);

    expect(styles.label!.fontFamily.toLowerCase()).toContain('inter');
    expect(parsePx(styles.label!.fontSize)).toBeGreaterThanOrEqual(12);
    expect(parsePx(styles.label!.fontSize)).toBeLessThanOrEqual(14);

    expect(styles.title!.fontFamily.toLowerCase()).toContain('space grotesk');
    expect(styles.title!.textTransform).not.toBe('uppercase');

    expect(styles.checkbox!.accentColor).toBe('rgb(99, 179, 46)');

    await page.screenshot({ path: path.join(OUT, 'screenshots', 'desktop-closed-panel.png') });
  });

  test('keyboard focus and mobile drawer have no horizontal overflow', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${resultsPath()}&_fp=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector(FILTER_SORT, { timeout: 60_000 });

    await page.locator(FILTER_SORT).focus();
    const focusStyles = await page.evaluate(() => {
      const el = document.querySelector('#ota-filter-sort');
      if (!el) return null;
      const cs = getComputedStyle(el);
      return { boxShadow: cs.boxShadow, outline: cs.outline, borderColor: cs.borderColor };
    });
    expect(focusStyles).not.toBeNull();
    expect(isBlueish(focusStyles!.boxShadow)).toBe(false);
    expect(isBlueish(focusStyles!.outline)).toBe(false);

    await page.screenshot({ path: path.join(OUT, 'screenshots', 'desktop-focused-select.png') });

    await page.locator(FILTER_CHECK).check();
    await page.screenshot({ path: path.join(OUT, 'screenshots', 'desktop-checkbox-checked.png') });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`${resultsPath()}&_fp=mobile`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.evaluate(() => {
      const btn = document.querySelector('[data-mobile-filter-open]') as HTMLButtonElement | null;
      btn?.click();
    });
    await page.waitForSelector(`${FILTER_PANEL}`, { state: 'visible', timeout: 30_000 });

    const overflow = await page.evaluate(() => {
      const panel = document.querySelector('.jp-filter-panel, .ota-filter-card[data-filter-drawer]') as HTMLElement | null;
      const docOverflow = document.documentElement.scrollWidth > document.documentElement.clientWidth;
      return {
        panelOverflow: panel ? panel.scrollWidth > panel.clientWidth : false,
        docOverflow,
      };
    });
    expect(overflow.panelOverflow).toBe(false);
    expect(overflow.docOverflow).toBe(false);

    await page.screenshot({ path: path.join(OUT, 'screenshots', 'mobile-filter-drawer.png') });
  });

  test('responsive screenshots for filter panel', async ({ page }) => {
    for (const vp of AUDIT_VIEWPORTS) {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      await page.goto(`${resultsPath()}&_fp=${vp.name}`, { waitUntil: 'networkidle', timeout: 120_000 });
      await page.waitForSelector(FILTER_PANEL, { state: 'attached', timeout: 60_000 });
      if (vp.width < 992) {
        await page.evaluate(() => {
          (document.querySelector('[data-mobile-filter-open]') as HTMLButtonElement | null)?.click();
        });
        await page.waitForSelector(FILTER_PANEL, { state: 'visible', timeout: 30_000 });
      }
      await page.screenshot({
        path: path.join(OUT, 'screenshots', `filter-panel-${vp.name}.png`),
        fullPage: false,
      });
    }
  });
});
