import { test, expect } from '@playwright/test';
import { PRIMARY_VIEWPORT, oneWayResultsUrl } from './helpers/constants';

const HOME_PATH = process.env.JETPK_HOME_PATH ?? '/';
const RESULTS_PATH = process.env.JETPK_RESULTS_PATH ?? oneWayResultsUrl().replace(/^\/jetpk/, '');

const HOME_DEPART = '#jp-flight-search [data-jp-date-role="depart"]';
const RESULTS_DEPART = '.jp-results-search-placement #jp-flight-search [data-jp-date-role="depart"]';

type StyleSnapshot = Record<string, string>;

const SHELL_PROPS = ['background', 'border', 'borderRadius', 'boxShadow', 'padding', 'display', 'gap'];
const TAB_PROPS = ['backgroundColor', 'color', 'borderColor', 'boxShadow', 'height', 'padding'];
const FIELD_PROPS = [
  'height',
  'minHeight',
  'paddingTop',
  'paddingRight',
  'paddingBottom',
  'paddingLeft',
  'borderTopWidth',
  'borderRadius',
  'backgroundColor',
  'boxShadow',
  'display',
  'alignItems',
];
const INNER_ROW_PROPS = [
  'display',
  'flexDirection',
  'alignItems',
  'gap',
  'flexWrap',
  'marginLeft',
  'marginRight',
  'lineHeight',
];
const BTN_PROPS = ['height', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'borderRadius', 'background', 'color', 'fontSize', 'lineHeight'];

async function readStyles(page: import('@playwright/test').Page, selector: string, props: string[]): Promise<StyleSnapshot | null> {
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

function assertParity(home: StyleSnapshot, results: StyleSnapshot, label: string): void {
  for (const key of Object.keys(home)) {
    expect(results[key], `${label}.${key}`).toBe(home[key]);
  }
}

function normalizeFieldDom(html: string): string {
  return html
    .replace(/\s(id|for|aria-controls|aria-labelledby)="[^"]*"/g, '')
    .replace(/\sstyle="[^"]*"/g, '')
    .replace(/\sdata-jp-[a-z-]+="[^"]*"/g, '')
    .replace(/\svalue="[^"]*"/g, '')
    .replace(/\sclass="\s+on\s+"/g, ' class="on"')
    .replace(/class="jp-field-value-row"/g, 'class="JP_INNER_ROW"')
    .replace(/class="row"/g, 'class="JP_INNER_ROW"')
    .replace(/<span class="jp-date-display[^"]*">[^<]*<\/span>/g, '<span class="jp-date-display is-placeholder">DATE</span>')
    .replace(/\s+/g, ' ')
    .trim();
}

test.describe('JetPK search live parity (home vs results)', () => {
  test('DOM structure + computed styles match at desktop width', async ({ page }) => {
    test.skip(!process.env.PLAYWRIGHT_BASE_URL && !process.env.JETPK_LIVE_BASE_URL, 'Requires PLAYWRIGHT_BASE_URL');

    await page.setViewportSize({ width: PRIMARY_VIEWPORT.width, height: PRIMARY_VIEWPORT.height });

    await page.goto(`${HOME_PATH}?_parity=${Date.now()}`, { waitUntil: 'networkidle', timeout: 90_000 });
    await page.waitForSelector('#jp-flight-search .field.jp-airport-field', { timeout: 60_000 });
    await page.evaluate(() => {
      const oneWay = document.querySelector('#segTrip button[data-jp-trip="one_way"]') as HTMLButtonElement | null;
      oneWay?.click();
      const field = document.querySelector('#jp-flight-search [data-jp-date-role="depart"]') as HTMLElement | null;
      if (field) field.hidden = false;
    });
    await page.waitForTimeout(300);

    const homeDom = await page.evaluate(() => {
      const departField = document.querySelector('#jp-flight-search [data-jp-date-role="depart"]');
      const departRow = departField?.querySelector('.jp-field-value-row') ?? departField?.querySelector('.row');
      return {
        search: document.querySelector('.search')?.outerHTML ?? '',
        dataJpSearch: document.querySelector('[data-jp-search]')?.outerHTML ?? '',
        activeTripTab: document.querySelector('#segTrip button.on')?.outerHTML ?? '',
        fromField: document.querySelector('#jp-flight-search .jp-airport-field')?.outerHTML ?? '',
        departField: departField?.outerHTML ?? '',
        paxField: document.querySelector('#jp-flight-search .jp-pax-field')?.outerHTML ?? '',
        departRow: departRow?.outerHTML ?? '',
        searchBtn: document.querySelector('#jp-flight-search .btn-search')?.outerHTML ?? '',
      };
    });

    const homeShell = await readStyles(page, '#jp-flight-search', SHELL_PROPS);
    const homeFrom = await readStyles(page, '#jp-flight-search .jp-airport-field', FIELD_PROPS);
    const homeDepart = await readStyles(page, HOME_DEPART, FIELD_PROPS);
    const homeDepartRow = await readStyles(page, `${HOME_DEPART} .jp-field-value-row, ${HOME_DEPART} .row`, INNER_ROW_PROPS);
    const homePax = await readStyles(page, '#jp-flight-search .jp-pax-field', FIELD_PROPS);
    const homePaxRow = await readStyles(page, '#jp-flight-search .jp-pax-field .jp-field-value-row', INNER_ROW_PROPS);
    const homeSearchBtn = await readStyles(page, '#jp-flight-search .btn-search.jp-search-submit', BTN_PROPS);
    const homeTripTab = await readStyles(page, '#segTrip button.on', TAB_PROPS);
    const homePillInd = await readStyles(page, '#segTrip .pill-ind', ['background', 'backgroundColor']);

    const homeDepartBBox = await page.evaluate(() => {
      const row = document.querySelector('#jp-flight-search [data-jp-date-role="depart"] .jp-field-value-row')
        ?? document.querySelector('#jp-flight-search [data-jp-date-role="depart"] .row');
      const icon = row?.querySelector('svg');
      const date = row?.querySelector('.jp-date-display');
      if (!icon || !date) return null;
      const ir = icon.getBoundingClientRect();
      const dr = date.getBoundingClientRect();
      return {
        iconX: ir.x,
        dateX: dr.x,
        vCenterDiff: Math.abs(ir.y + ir.height / 2 - (dr.y + dr.height / 2)),
        sameRow: Math.abs(ir.y - dr.y) < 5 && dr.x > ir.x,
      };
    });

    await page.goto(`${RESULTS_PATH}${RESULTS_PATH.includes('?') ? '&' : '?'}_parity=${Date.now()}`, { waitUntil: 'networkidle', timeout: 120_000 });
    await page.waitForSelector('.jp-results-search-placement #jp-flight-search .field.jp-airport-field', { timeout: 60_000 });

    const resultsDom = await page.evaluate(() => {
      const departField = document.querySelector('.jp-results-search-placement #jp-flight-search [data-jp-date-role="depart"]');
      const departRow = departField?.querySelector('.jp-field-value-row') ?? departField?.querySelector('.row');
      return {
        search: document.querySelector('.jp-results-search-placement .search')?.outerHTML ?? '',
        dataJpSearch: document.querySelector('.jp-results-search-placement [data-jp-search]')?.outerHTML ?? '',
        activeTripTab: document.querySelector('.jp-results-search-placement #segTrip button.on')?.outerHTML ?? '',
        fromField: document.querySelector('.jp-results-search-placement #jp-flight-search .jp-airport-field')?.outerHTML ?? '',
        departField: departField?.outerHTML ?? '',
        paxField: document.querySelector('.jp-results-search-placement #jp-flight-search .jp-pax-field')?.outerHTML ?? '',
        departRow: departRow?.outerHTML ?? '',
        searchBtn: document.querySelector('.jp-results-search-placement #jp-flight-search .btn-search')?.outerHTML ?? '',
      };
    });

    const resultsShell = await readStyles(page, '.jp-results-search-placement #jp-flight-search', SHELL_PROPS);
    const resultsFrom = await readStyles(page, '.jp-results-search-placement #jp-flight-search .jp-airport-field', FIELD_PROPS);
    const resultsDepart = await readStyles(page, RESULTS_DEPART, FIELD_PROPS);
    const resultsDepartRow = await readStyles(page, `${RESULTS_DEPART} .jp-field-value-row, ${RESULTS_DEPART} .row`, INNER_ROW_PROPS);
    const resultsPax = await readStyles(page, '.jp-results-search-placement #jp-flight-search .jp-pax-field', FIELD_PROPS);
    const resultsPaxRow = await readStyles(page, '.jp-results-search-placement #jp-flight-search .jp-pax-field .jp-field-value-row', INNER_ROW_PROPS);
    const resultsSearchBtn = await readStyles(page, '.jp-results-search-placement #jp-flight-search .btn-search.jp-search-submit', BTN_PROPS);
    const resultsTripTab = await readStyles(page, '.jp-results-search-placement #segTrip button.on', TAB_PROPS);
    const resultsPillInd = await readStyles(page, '.jp-results-search-placement #segTrip .pill-ind', ['background', 'backgroundColor']);

    const resultsDepartBBox = await page.evaluate(() => {
      const row = document.querySelector('.jp-results-search-placement #jp-flight-search [data-jp-date-role="depart"] .jp-field-value-row')
        ?? document.querySelector('.jp-results-search-placement #jp-flight-search [data-jp-date-role="depart"] .row');
      const icon = row?.querySelector('svg');
      const date = row?.querySelector('.jp-date-display');
      if (!icon || !date) return null;
      const ir = icon.getBoundingClientRect();
      const dr = date.getBoundingClientRect();
      return {
        iconX: ir.x,
        dateX: dr.x,
        vCenterDiff: Math.abs(ir.y + ir.height / 2 - (dr.y + dr.height / 2)),
        sameRow: Math.abs(ir.y - dr.y) < 5 && dr.x > ir.x,
      };
    });

    const domKeys = ['activeTripTab', 'fromField', 'departField', 'paxField', 'departRow', 'searchBtn'] as const;
    for (const key of domKeys) {
      expect(homeDom[key].length, `home DOM missing: ${key}`).toBeGreaterThan(0);
      expect(resultsDom[key].length, `results DOM missing: ${key}`).toBeGreaterThan(0);
      expect(normalizeFieldDom(resultsDom[key]), `DOM mismatch: ${key}`).toBe(normalizeFieldDom(homeDom[key]));
    }

    expect(homeDepartRow).not.toBeNull();
    expect(resultsDepartRow).not.toBeNull();
    expect(homeDepartBBox).not.toBeNull();
    expect(resultsDepartBBox).not.toBeNull();

    if (homeShell && resultsShell) assertParity(homeShell, resultsShell, 'shell');
    if (homeFrom && resultsFrom) assertParity(homeFrom, resultsFrom, 'fromField');
    if (homeDepart && resultsDepart) assertParity(homeDepart, resultsDepart, 'departField');
    if (homeDepartRow && resultsDepartRow) assertParity(homeDepartRow, resultsDepartRow, 'departInnerRow');
    if (homePax && resultsPax) assertParity(homePax, resultsPax, 'paxField');
    if (homePaxRow && resultsPaxRow) assertParity(homePaxRow, resultsPaxRow, 'paxInnerRow');
    if (homeSearchBtn && resultsSearchBtn) assertParity(homeSearchBtn, resultsSearchBtn, 'searchBtn');
    if (homeTripTab && resultsTripTab) assertParity(homeTripTab, resultsTripTab, 'activeTripTab');
    if (homePillInd && resultsPillInd) assertParity(homePillInd, resultsPillInd, 'tripPillInd');

    if (homeDepartBBox && resultsDepartBBox) {
      expect(resultsDepartBBox.sameRow, 'depart icon/date must share one row').toBe(true);
      expect(resultsDepartBBox.dateX, 'date must be to the right of icon').toBeGreaterThan(resultsDepartBBox.iconX);
      expect(resultsDepartBBox.vCenterDiff, 'depart vertical center diff <= 2px').toBeLessThanOrEqual(2);
      assertParity(
        { sameRow: String(homeDepartBBox.sameRow), vCenterDiff: String(homeDepartBBox.vCenterDiff) },
        { sameRow: String(resultsDepartBBox.sameRow), vCenterDiff: String(resultsDepartBBox.vCenterDiff) },
        'departBBox',
      );
    }
  });
});
