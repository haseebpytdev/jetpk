/**
 * JETPK-SHARED-HEADER-RESULTS-FILTER-UI-AND-PROJECT-WIDE-HIGHLIGHT-ROOT-CAUSE-FIX
 * Header Register parity, filter theming, and focus/highlight regression.
 */
import { test, expect } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';
import { AUDIT_VIEWPORTS, futureDepartDate } from './helpers/constants';

const OUT = 'storage/app/audits/jetpk-header-filter-parity';

type StyleMap = Record<string, string>;

const HEADER_REGISTER = '.jp-site-header .jp-header-register, .jp-register-menu__trigger';
const FILTER_SORT = '#ota-filter-sort, [data-filter-sort]';
const FILTER_CHECK = '[data-filter-refundable].jp-filter-check, [data-filter-refundable]';

const REGISTER_PROPS = [
  'backgroundColor',
  'color',
  'borderColor',
  'borderWidth',
  'borderRadius',
  'height',
  'paddingTop',
  'paddingRight',
  'paddingBottom',
  'paddingLeft',
  'fontFamily',
  'fontSize',
  'fontWeight',
  'boxShadow',
];

const FILTER_SELECT_PROPS = [
  'fontFamily',
  'fontSize',
  'fontWeight',
  'height',
  'minHeight',
  'borderRadius',
  'borderTopWidth',
  'backgroundColor',
  'color',
  'appearance',
  'boxShadow',
  'outline',
];

const FILTER_CHECK_PROPS = ['accentColor', 'width', 'height', 'fontFamily'];

function resultsPath(): string {
  const depart = futureDepartDate();
  return `/flights/results?trip_type=one_way&from=ISB&to=KHI&from_display=Islamabad&to_display=Karachi&depart=${depart}&adults=1&children=0&infants=0&cabin=economy`;
}

async function readStyles(
  page: import('@playwright/test').Page,
  selector: string,
  props: string[],
): Promise<StyleMap | null> {
  return page.evaluate(
    ({ sel, propertyNames }) => {
      const el = document.querySelector(sel);
      if (!el) return null;
      const cs = window.getComputedStyle(el);
      const out: Record<string, string> = {};
      for (const p of propertyNames) out[p] = (cs as unknown as Record<string, string>)[p];
      return out;
    },
    { sel: selector, propertyNames: props },
  );
}

function isBlueish(value: string): boolean {
  const v = value.toLowerCase();
  if (v.includes('2563eb') || v.includes('37, 99, 235') || v.includes('59, 130, 246') || v.includes('93c5fd')) {
    return true;
  }
  if (v.startsWith('rgb(')) {
    const m = v.match(/rgb\((\d+),\s*(\d+),\s*(\d+)\)/);
    if (m) {
      const b = Number(m[3]);
      const r = Number(m[1]);
      return b > 180 && b > r + 40;
    }
  }
  return false;
}

function normalizeHeaderDom(html: string): string {
  return html
    .replace(/\sclass="[^"]*scrolled[^"]*"/g, ' class="HEADER"')
    .replace(/\sclass="active"/g, '')
    .replace(/\sclass=""/g, '')
    .replace(/\saria-expanded="[^"]*"/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

test.describe('JetPK header + filter + highlight parity', () => {
  test.beforeAll(() => {
    fs.mkdirSync(OUT, { recursive: true });
    fs.mkdirSync(path.join(OUT, 'screenshots'), { recursive: true });
  });

  test('header Register computed-style parity (home vs results)', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });

    await page.goto(`/?_hf=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector(HEADER_REGISTER, { timeout: 60_000 });
    const homeRegister = await readStyles(page, HEADER_REGISTER, REGISTER_PROPS);
    const homeHeaderHtml = await page.evaluate(() => document.querySelector('.jp-site-header, #header')?.outerHTML ?? '');

    await page.goto(`${resultsPath()}&_hf=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector(HEADER_REGISTER, { timeout: 60_000 });
    const resultsRegister = await readStyles(page, HEADER_REGISTER, REGISTER_PROPS);
    const resultsHeaderHtml = await page.evaluate(() => document.querySelector('.jp-site-header, #header')?.outerHTML ?? '');

    expect(homeRegister, 'home register styles').not.toBeNull();
    expect(resultsRegister, 'results register styles').not.toBeNull();

    const parityKeys = [
      'color',
      'borderColor',
      'borderWidth',
      'borderRadius',
      'fontFamily',
      'fontWeight',
    ] as const;

    for (const key of parityKeys) {
      expect(resultsRegister![key], `register.${key}`).toBe(homeRegister![key]);
    }

    expect(resultsRegister!.backgroundColor, 'register must not be white surface').not.toBe('rgb(255, 255, 255)');
    expect(resultsRegister!.color, 'register text must be light on green').toBe('rgb(255, 255, 255)');

    expect(normalizeHeaderDom(resultsHeaderHtml)).toBe(normalizeHeaderDom(homeHeaderHtml));
  });

  test('filter controls use JetPakistan design tokens', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${resultsPath()}&_hf=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector(FILTER_SORT, { timeout: 60_000 });

    const selectStyles = await readStyles(page, FILTER_SORT, FILTER_SELECT_PROPS);
    const checkStyles = await readStyles(page, FILTER_CHECK, FILTER_CHECK_PROPS);

    expect(selectStyles).not.toBeNull();
    expect(checkStyles).not.toBeNull();

    expect(selectStyles!.fontFamily.toLowerCase()).toContain('inter');
    expect(selectStyles!.appearance).toBe('none');
    expect(isBlueish(selectStyles!.boxShadow)).toBe(false);
    expect(isBlueish(selectStyles!.outline)).toBe(false);

    expect(checkStyles!.accentColor).not.toBe('rgb(37, 99, 235)');
    expect(['0px', `${checkStyles!.width}`]).toContain(checkStyles!.height);

    await page.click(FILTER_SORT);
    const afterClick = await readStyles(page, FILTER_SORT, ['boxShadow', 'outline', 'borderColor']);
    expect(isBlueish(afterClick!.boxShadow)).toBe(false);
    expect(isBlueish(afterClick!.outline)).toBe(false);
  });

  test('keyboard focus-visible uses JetPakistan ring; mouse click avoids blue glow', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(`${resultsPath()}&_hf=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector(HEADER_REGISTER, { timeout: 60_000 });

    const targets = [
      { name: 'register', selector: HEADER_REGISTER },
      { name: 'searchBtn', selector: '.jp-results-search-placement #jp-flight-search .btn-search' },
      { name: 'filterSelect', selector: FILTER_SORT },
      { name: 'filterCheck', selector: FILTER_CHECK },
    ];

    for (const t of targets) {
      const el = page.locator(t.selector).first();
      await el.click({ force: true });
      const clickStyles = await readStyles(page, t.selector, ['boxShadow', 'outline', 'outlineColor', 'borderColor']);
      expect(clickStyles, `${t.name} click styles`).not.toBeNull();
      expect(isBlueish(clickStyles!.boxShadow), `${t.name} click box-shadow`).toBe(false);
      expect(isBlueish(clickStyles!.outlineColor || clickStyles!.outline), `${t.name} click outline`).toBe(false);
    }

    await page.keyboard.press('Tab');
    await page.keyboard.press('Tab');
    const focused = await page.evaluate(() => {
      const el = document.activeElement;
      if (!el) return null;
      const cs = window.getComputedStyle(el);
      return {
        tag: el.tagName,
        boxShadow: cs.boxShadow,
        outline: cs.outline,
        outlineColor: cs.outlineColor,
      };
    });
    expect(focused).not.toBeNull();
    if (focused!.boxShadow !== 'none' || focused!.outlineWidth !== '0px') {
      expect(isBlueish(focused!.boxShadow)).toBe(false);
      expect(isBlueish(focused!.outlineColor || focused!.outline)).toBe(false);
    }
  });

  test('responsive screenshots (before/after evidence)', async ({ page }) => {
    const shots: { name: string; path: string; url: string }[] = [
      { name: 'home-desktop-header', path: '/', url: '/' },
      { name: 'results-desktop-header', path: resultsPath(), url: resultsPath() },
      { name: 'results-filter-sidebar', path: resultsPath(), url: resultsPath() },
    ];

    for (const vp of AUDIT_VIEWPORTS) {
      await page.setViewportSize({ width: vp.width, height: vp.height });
      for (const shot of shots) {
        await page.goto(`${shot.url}&_ss=${vp.name}`, { waitUntil: 'networkidle', timeout: 120_000 });
        if (shot.name.includes('filter')) {
          await page.waitForSelector('.jp-filter-panel, .ota-filter-card', { timeout: 60_000 }).catch(() => undefined);
        } else {
          await page.waitForSelector('.jp-site-header, #header', { timeout: 60_000 });
        }
        const file = path.join(OUT, 'screenshots', `${shot.name}-${vp.name}.png`);
        await page.screenshot({ path: file, fullPage: false });
      }
    }
  });
});
