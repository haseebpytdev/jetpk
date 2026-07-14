import type { Page } from '@playwright/test';
import {
  CLIENT_PREFIX,
  FORBIDDEN_HREF_PATTERNS,
  FORBIDDEN_TEXT_PATTERNS,
  MASTER_CARD_SELECTORS,
} from './constants';
import type { LeakHit } from './audit-state';

export async function scanPageForLeaks(
  page: Page,
  ctx: { pageKey: string; viewport: string },
): Promise<LeakHit[]> {
  const hits: LeakHit[] = [];

  const bodyText = await page.locator('body').innerText().catch(() => '');
  const html = await page.content().catch(() => '');

  for (const pattern of FORBIDDEN_TEXT_PATTERNS) {
    if (bodyText.includes(pattern)) {
      hits.push({
        page: ctx.pageKey,
        viewport: ctx.viewport,
        kind: 'text',
        pattern,
        detail: `Visible text contains "${pattern}"`,
        severity: 'fail',
      });
    } else if (html.includes(pattern) && html.includes(`<!--`) && html.includes(pattern)) {
      // comment-only — warning
      const inComment = /<!--[\s\S]*?-->/.test(html) && html.match(/<!--[\s\S]*?-->/)?.[0]?.includes(pattern);
      if (inComment) {
        hits.push({
          page: ctx.pageKey,
          viewport: ctx.viewport,
          kind: 'text',
          pattern,
          detail: `Comment-only reference to "${pattern}"`,
          severity: 'warn',
        });
      }
    }
  }

  const assetUrls = await page.evaluate(() => {
    const urls: { kind: string; url: string }[] = [];
    document.querySelectorAll('link[rel="stylesheet"][href]').forEach((el) => {
      urls.push({ kind: 'css', url: (el as HTMLLinkElement).href });
    });
    document.querySelectorAll('script[src]').forEach((el) => {
      urls.push({ kind: 'js', url: (el as HTMLScriptElement).src });
    });
    document.querySelectorAll('img[src]').forEach((el) => {
      urls.push({ kind: 'img', url: (el as HTMLImageElement).src });
    });
    document.querySelectorAll('a[href]').forEach((el) => {
      urls.push({ kind: 'href', url: (el as HTMLAnchorElement).href });
    });
    document.querySelectorAll('form[action]').forEach((el) => {
      urls.push({ kind: 'form', url: (el as HTMLFormElement).action });
    });
    return urls;
  });

  for (const asset of assetUrls) {
    if (asset.kind === 'css' && /ota-public\.css/i.test(asset.url) && !asset.url.includes('jetpakistan')) {
      hits.push({
        page: ctx.pageKey,
        viewport: ctx.viewport,
        kind: 'asset',
        pattern: 'ota-public.css',
        detail: `Master CSS loaded: ${asset.url}`,
        severity: 'fail',
      });
    }

    if (asset.kind === 'href' || asset.kind === 'form') {
      try {
        const u = new URL(asset.url);
        const p = u.pathname;
        if (p.startsWith('/jetpk')) continue;
        for (const rule of FORBIDDEN_HREF_PATTERNS) {
          if (rule.pattern.test(p) && !p.startsWith(`${CLIENT_PREFIX}`)) {
            hits.push({
              page: ctx.pageKey,
              viewport: ctx.viewport,
              kind: 'href',
              pattern: rule.label,
              detail: `${asset.kind} points to ${p}`,
              severity: 'fail',
            });
          }
        }
      } catch {
        /* ignore malformed URLs */
      }
    }
  }

  for (const sel of MASTER_CARD_SELECTORS) {
    const count = await page.locator(sel).count();
    if (count > 0) {
      hits.push({
        page: ctx.pageKey,
        viewport: ctx.viewport,
        kind: 'class',
        pattern: sel,
        detail: `Master card selector matched ${count} element(s)`,
        severity: 'fail',
      });
    }
  }

  const bodyClass = await page.locator('body').getAttribute('class').catch(() => '');
  if (bodyClass?.includes('jp-flights-results') === false && ctx.pageKey.includes('results')) {
  }

  return hits;
}

export async function assertJetPkResultsPage(page: Page): Promise<string | null> {
  const hasBody = await page.locator('body.jp-flights-results').count();
  if (!hasBody) {
    return 'Missing body.jp-flights-results on JetPK results page';
  }
  return null;
}

export async function assertJetPkCardsPresent(page: Page): Promise<{ ok: boolean; detail: string }> {
  const jpCards = await page.locator('.jp-flight-card').count();
  const skeleton = await page.locator('.ota-result-skeleton-card').count();
  const empty = await page.locator('[data-results-empty], [data-return-empty-message]:not([hidden])').count();

  if (jpCards > 0) {
    return { ok: true, detail: `${jpCards} JetPK card(s) rendered` };
  }
  if (skeleton > 0) {
    return { ok: false, detail: 'Results still loading (skeleton cards visible)' };
  }
  if (empty > 0) {
    return { ok: false, detail: 'No results returned (empty state)' };
  }
  return { ok: false, detail: 'No JetPK cards found on page' };
}

export type SearchShellBox = {
  x: number;
  y: number;
  width: number;
  height: number;
};

export type SearchShellFingerprint = {
  exists: boolean;
  className: string;
  classTokens: string[];
  fieldRowVisible: boolean;
  searchButtonVisible: boolean;
  box: SearchShellBox | null;
  tabs: string;
  submitText: string;
  hasFrom: boolean;
  hasTo: boolean;
  hasDirect: boolean;
};

const EMPTY_SEARCH_SHELL: SearchShellFingerprint = {
  exists: false,
  className: '',
  classTokens: [],
  fieldRowVisible: false,
  searchButtonVisible: false,
  box: null,
  tabs: '',
  submitText: '',
  hasFrom: false,
  hasTo: false,
  hasDirect: false,
};

export async function collectSearchShellFingerprint(page: Page): Promise<SearchShellFingerprint> {
  const root = page.locator('[data-jp-search]').first();
  const exists = (await root.count()) > 0;
  if (!exists) {
    return { ...EMPTY_SEARCH_SHELL };
  }

  const tabs = await root.locator('[data-jp-trip-tabs] button[data-jp-trip]').allTextContents();
  const submitText = await root.locator('[data-jp-flight-submit] .jp-search-submit-text').textContent().catch(() => '');
  const hasFrom = (await root.locator('[data-jp-airport-code="from"]').count()) > 0;
  const hasTo = (await root.locator('[data-jp-airport-code="to"]').count()) > 0;
  const hasDirect = (await root.locator('input[name="direct_only"], [data-jp-direct-only]').count()) > 0;
  const fieldRowVisible =
    (await root.locator('.jp-search-row .field, .jp-airport-field').count()) >= 2;
  const searchButtonVisible = (await root.locator('[data-jp-flight-submit]').count()) > 0;

  const shellMeta = await root.evaluate((el) => {
    const className = typeof el.className === 'string' ? el.className : '';
    const rect = el.getBoundingClientRect();

    return {
      className,
      classTokens: className
        .split(/\s+/)
        .map((token) => token.trim())
        .filter(Boolean),
      box: {
        x: rect.x,
        y: rect.y,
        width: rect.width,
        height: rect.height,
      },
    };
  }).catch(() => ({
    className: '',
    classTokens: [] as string[],
    box: null as SearchShellBox | null,
  }));

  return {
    exists: true,
    className: shellMeta.className ?? '',
    classTokens: Array.isArray(shellMeta.classTokens) ? shellMeta.classTokens : [],
    fieldRowVisible,
    searchButtonVisible,
    box: shellMeta.box,
    tabs: tabs.map((t) => t.trim()).join('|'),
    submitText: (submitText ?? '').trim(),
    hasFrom,
    hasTo,
    hasDirect,
  };
}
