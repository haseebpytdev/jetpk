/**
 * One-off live investigation: Home vs Results DOM + computed styles.
 * Run: JETPK_LIVE_BASE_URL=https://jetpakistan.pk npx playwright test investigate-live-dom-styles --config=playwright.jetpk-investigate.config.ts
 */
import { test } from '@playwright/test';
import * as fs from 'fs';
import * as path from 'path';

const OUT = 'storage/app/audits/jetpk-dom-investigation';

function futureDepart(): string {
  const d = new Date();
  d.setDate(d.getDate() + 21);
  return d.toISOString().slice(0, 10);
}

test('investigate home vs results live DOM and styles', async ({ page }) => {
  fs.mkdirSync(OUT, { recursive: true });
  await page.setViewportSize({ width: 1440, height: 900 });

  // Home
  await page.goto('/', { waitUntil: 'networkidle', timeout: 120_000 });
  await page.waitForSelector('#jp-flight-search', { timeout: 60_000 });
  await page.screenshot({ path: path.join(OUT, 'home-before.png'), fullPage: false });

  const homeData = await page.evaluate(() => {
    const pick = (sel: string) => document.querySelector(sel);
    const styles = (el: Element | null, props: string[]) => {
      if (!el) return null;
      const cs = getComputedStyle(el);
      const out: Record<string, string> = {};
      for (const p of props) out[p] = (cs as unknown as Record<string, string>)[p];
      return out;
    };

    const shell = pick('.search');
    const search = pick('[data-jp-search]');
    const tripTabs = pick('#segTrip');
    const activeTripTab = pick('#segTrip button.on');
    const pillInd = pick('#segTrip .pill-ind');
    const fromField = pick('#jp-flight-search .jp-airport-field');
    const departField = pick('#jp-flight-search .jp-date-field');
    const paxField = pick('#jp-flight-search .jp-pax-field');
    const departRow = pick('#jp-flight-search .jp-date-field .jp-field-value-row');
    const paxRow = pick('#jp-flight-search .jp-pax-field .jp-field-value-row');
    const searchBtn = pick('#jp-flight-search .btn-search');
    const stylesheets = [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => (l as HTMLLinkElement).href);

    return {
      stylesheets,
      outerHTML: {
        search: shell?.outerHTML?.slice(0, 2000),
        dataJpSearch: search?.outerHTML?.slice(0, 500),
        activeTripTab: activeTripTab?.outerHTML,
        departRow: departRow?.outerHTML,
        paxRow: paxRow?.outerHTML,
        searchBtn: searchBtn?.outerHTML,
      },
      computed: {
        shell: styles(shell, ['background', 'border', 'borderRadius', 'boxShadow', 'padding', 'display', 'gap']),
        activeTripTab: styles(activeTripTab, ['backgroundColor', 'color', 'borderColor', 'boxShadow', 'height', 'padding']),
        pillInd: styles(pillInd, ['background', 'backgroundColor', 'left', 'width']),
        fromField: styles(fromField, ['height', 'minHeight', 'padding', 'border', 'borderRadius', 'background', 'boxShadow', 'display', 'alignItems']),
        departField: styles(departField, ['height', 'minHeight', 'padding', 'border', 'borderRadius', 'background', 'display', 'alignItems']),
        departRow: styles(departRow, ['display', 'flexDirection', 'alignItems', 'gap', 'lineHeight', 'flexWrap', 'marginLeft', 'marginRight']),
        departIcon: styles(departRow?.querySelector('svg') ?? null, ['width', 'height', 'display']),
        departDate: styles(departRow?.querySelector('.jp-date-display') ?? null, ['display', 'lineHeight']),
        paxRow: styles(paxRow, ['display', 'flexDirection', 'alignItems', 'gap', 'flexWrap', 'marginLeft', 'marginRight']),
        searchBtn: styles(searchBtn, ['height', 'padding', 'borderRadius', 'background', 'color', 'fontSize', 'lineHeight']),
      },
      departBBox: (() => {
        const icon = departRow?.querySelector('svg');
        const date = departRow?.querySelector('.jp-date-display');
        if (!icon || !date) return null;
        const ir = icon.getBoundingClientRect();
        const dr = date.getBoundingClientRect();
        return {
          icon: { x: ir.x, y: ir.y, w: ir.width, h: ir.height },
          date: { x: dr.x, y: dr.y, w: dr.width, h: dr.height },
          sameRow: Math.abs(ir.y - dr.y) < 5 && dr.x > ir.x,
          vCenterDiff: Math.abs(ir.y + ir.height / 2 - (dr.y + dr.height / 2)),
        };
      })(),
    };
  });

  // Results via fixture URL (no supplier search from SSH)
  const depart = futureDepart();
  const resultsUrl = `/flights/results?trip_type=one_way&from=ISB&to=KHI&from_display=Islamabad&to_display=Karachi&depart=${depart}&adults=1&children=0&infants=0&cabin=economy`;
  await page.goto(resultsUrl, { waitUntil: 'networkidle', timeout: 120_000 });
  await page.waitForSelector('.jp-results-search-placement #jp-flight-search', { timeout: 60_000 });
  await page.screenshot({ path: path.join(OUT, 'results-before.png'), fullPage: false });

  const resultsData = await page.evaluate(() => {
    const pick = (sel: string) => document.querySelector(sel);
    const styles = (el: Element | null, props: string[]) => {
      if (!el) return null;
      const cs = getComputedStyle(el);
      const out: Record<string, string> = {};
      for (const p of props) out[p] = (cs as unknown as Record<string, string>)[p];
      return out;
    };

    const shell = pick('.jp-results-search-placement .search');
    const search = pick('.jp-results-search-placement [data-jp-search]');
    const tripTabs = pick('.jp-results-search-placement #segTrip');
    const activeTripTab = pick('.jp-results-search-placement #segTrip button.on');
    const pillInd = pick('.jp-results-search-placement #segTrip .pill-ind');
    const fromField = pick('.jp-results-search-placement #jp-flight-search .jp-airport-field');
    const departField = pick('.jp-results-search-placement #jp-flight-search .jp-date-field');
    const paxField = pick('.jp-results-search-placement #jp-flight-search .jp-pax-field');
    const departRow = pick('.jp-results-search-placement #jp-flight-search .jp-date-field .jp-field-value-row');
    const paxRow = pick('.jp-results-search-placement #jp-flight-search .jp-pax-field .jp-field-value-row');
    const searchBtn = pick('.jp-results-search-placement #jp-flight-search .btn-search');
    const stylesheets = [...document.querySelectorAll('link[rel="stylesheet"]')].map((l) => (l as HTMLLinkElement).href);

    return {
      stylesheets,
      outerHTML: {
        search: shell?.outerHTML?.slice(0, 2000),
        dataJpSearch: search?.outerHTML?.slice(0, 500),
        activeTripTab: activeTripTab?.outerHTML,
        departRow: departRow?.outerHTML,
        paxRow: paxRow?.outerHTML,
        searchBtn: searchBtn?.outerHTML,
      },
      computed: {
        shell: styles(shell, ['background', 'border', 'borderRadius', 'boxShadow', 'padding', 'display', 'gap']),
        activeTripTab: styles(activeTripTab, ['backgroundColor', 'color', 'borderColor', 'boxShadow', 'height', 'padding']),
        pillInd: styles(pillInd, ['background', 'backgroundColor', 'left', 'width']),
        fromField: styles(fromField, ['height', 'minHeight', 'padding', 'border', 'borderRadius', 'background', 'boxShadow', 'display', 'alignItems']),
        departField: styles(departField, ['height', 'minHeight', 'padding', 'border', 'borderRadius', 'background', 'display', 'alignItems']),
        departRow: styles(departRow, ['display', 'flexDirection', 'alignItems', 'gap', 'lineHeight', 'flexWrap', 'marginLeft', 'marginRight']),
        departIcon: styles(departRow?.querySelector('svg') ?? null, ['width', 'height', 'display']),
        departDate: styles(departRow?.querySelector('.jp-date-display') ?? null, ['display', 'lineHeight']),
        paxRow: styles(paxRow, ['display', 'flexDirection', 'alignItems', 'gap', 'flexWrap', 'marginLeft', 'marginRight']),
        searchBtn: styles(searchBtn, ['height', 'padding', 'borderRadius', 'background', 'color', 'fontSize', 'lineHeight']),
      },
      departBBox: (() => {
        const icon = departRow?.querySelector('svg');
        const date = departRow?.querySelector('.jp-date-display');
        if (!icon || !date) return null;
        const ir = icon.getBoundingClientRect();
        const dr = date.getBoundingClientRect();
        return {
          icon: { x: ir.x, y: ir.y, w: ir.width, h: ir.height },
          date: { x: dr.x, y: dr.y, w: dr.width, h: dr.height },
          sameRow: Math.abs(ir.y - dr.y) < 5 && dr.x > ir.x,
          vCenterDiff: Math.abs(ir.y + ir.height / 2 - (dr.y + dr.height / 2)),
        };
      })(),
    };
  });

  const diff: Record<string, unknown> = {};
  for (const section of ['computed'] as const) {
    const h = homeData[section] as Record<string, Record<string, string> | null>;
    const r = resultsData[section] as Record<string, Record<string, string> | null>;
    for (const key of Object.keys(h)) {
      const hv = h[key];
      const rv = r[key];
      if (!hv || !rv) continue;
      const propDiff: Record<string, { home: string; results: string }> = {};
      for (const prop of Object.keys(hv)) {
        if (hv[prop] !== rv[prop]) propDiff[prop] = { home: hv[prop], results: rv[prop] };
      }
      if (Object.keys(propDiff).length) diff[key] = propDiff;
    }
  }

  fs.writeFileSync(path.join(OUT, 'home.json'), JSON.stringify(homeData, null, 2));
  fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(resultsData, null, 2));
  fs.writeFileSync(path.join(OUT, 'diff.json'), JSON.stringify(diff, null, 2));
  console.log('STYLE DIFF:', JSON.stringify(diff, null, 2));
});
