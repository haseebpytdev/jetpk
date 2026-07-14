import type { Page, Response } from '@playwright/test';

/** Total budget per navigation (goto + shell); aligned with Playwright navigationTimeout in public configs. */
export const PUBLIC_NAV_TIMEOUT_MS = 25_000;

const SEARCH_SHELL_KEYS = new Set(['home', 'flights-search']);

export function publicPageShellLocator(page: Page, pageKey: string) {
  if (SEARCH_SHELL_KEYS.has(pageKey)) {
    return page.locator('[data-hero-search], main, .ota-main-nav').first();
  }
  if (pageKey === 'flights-results') {
    return page.locator('.ota-results-pro, [data-results-root], [data-hero-search], main').first();
  }
  return page.locator('main, [data-hero-search], form, .ota-main-nav').first();
}

function formatNavError(err: unknown): string {
  if (err instanceof Error) {
    return err.message;
  }
  return String(err);
}

/**
 * Fast public navigation: commit (not domcontentloaded), shell wait, one retry.
 * Layout assertions run after this; broken UI still fails checks.
 */
export async function gotoPublicPage(
  page: Page,
  path: string,
  pageKey: string,
  options?: { timeoutMs?: number },
): Promise<Response | null> {
  const budget = options?.timeoutMs ?? PUBLIC_NAV_TIMEOUT_MS;
  const started = Date.now();
  const shell = publicPageShellLocator(page, pageKey);
  let lastError: unknown;

  for (let attempt = 0; attempt < 2; attempt += 1) {
    const elapsed = Date.now() - started;
    const remaining = budget - elapsed;
    if (remaining < 2_500) {
      break;
    }

    const gotoTimeout = Math.min(Math.floor(budget / 2), remaining);
    let response: Response | null = null;

    try {
      response = await page.goto(path, {
        waitUntil: 'commit',
        timeout: gotoTimeout,
      });
    } catch (err) {
      lastError = err;
      if (attempt === 0) {
        continue;
      }
      break;
    }

    const shellTimeout = Math.max(3_000, budget - (Date.now() - started));
    try {
      await shell.waitFor({ state: 'attached', timeout: shellTimeout });
      if (SEARCH_SHELL_KEYS.has(pageKey)) {
        await page
          .locator('[data-hero-search]')
          .first()
          .waitFor({ state: 'visible', timeout: Math.min(8_000, shellTimeout) });
      } else if (pageKey === 'flights-results') {
        await page
          .locator('.ota-results-pro, [data-results-root], main')
          .first()
          .waitFor({ state: 'visible', timeout: Math.min(8_000, shellTimeout) });
      }
      return response;
    } catch (err) {
      lastError = err;
      if (attempt === 0) {
        continue;
      }
    }
  }

  throw new Error(
    `${pageKey} navigation failed within ${budget}ms (${formatNavError(lastError)})`,
  );
}
