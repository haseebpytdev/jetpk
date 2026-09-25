import { expect, test, type Locator, type Page } from '@playwright/test';

type UiPage = {
  slug: string;
  path: string;
};

const pages: UiPage[] = [
  { slug: 'home', path: '/' },
  { slug: 'flights-search', path: '/flights/search' },
  { slug: 'flights-results', path: '/flights/results?from=LHE&to=DXB&depart=2026-06-20&trip_type=one_way&cabin=economy&adults=1&children=0&infants=0' },
  { slug: 'lookup-booking', path: '/lookup-booking' },
  { slug: 'support', path: '/support' },
  { slug: 'about-us', path: '/about-us' },
  { slug: 'login', path: '/login' },
  { slug: 'register', path: '/register' },
  { slug: 'register-agent', path: '/agent/register/apply' },
  { slug: 'agent-network', path: '/agent-network' },
];

const viewports = [
  { width: 1440, height: 900 },
  { width: 1200, height: 800 },
  { width: 1024, height: 768 },
  { width: 768, height: 900 },
  { width: 720, height: 900 },
  { width: 480, height: 900 },
];

/** Matches CSS: desktop nav from 992px, mobile menu ≤991px */
const desktopNavMinWidth = 992;
const mobileNavMaxWidth = 991;

async function hasHorizontalOverflow(page: Page): Promise<boolean> {
  return page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 2);
}

async function hasObviousFormOverlap(page: Page): Promise<boolean> {
  return page.evaluate(() => {
    const controls = Array.from(document.querySelectorAll('input, select, textarea, button'))
      .filter((el) => {
        const node = el as HTMLElement;
        const rect = node.getBoundingClientRect();
        const style = window.getComputedStyle(node);
        return rect.width > 0 && rect.height > 0 && style.visibility !== 'hidden' && style.display !== 'none';
      }) as HTMLElement[];
    for (let i = 0; i < controls.length; i++) {
      const a = controls[i].getBoundingClientRect();
      for (let j = i + 1; j < controls.length; j++) {
        const b = controls[j].getBoundingClientRect();
        const overlapX = Math.max(0, Math.min(a.right, b.right) - Math.max(a.left, b.left));
        const overlapY = Math.max(0, Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top));
        if (overlapX > 8 && overlapY > 8) {
          return true;
        }
      }
    }
    return false;
  });
}

async function hasLowContrastPrimaryButtons(page: Page): Promise<boolean> {
  return page.evaluate(() => {
    const candidates = Array.from(document.querySelectorAll('a, button')).filter((el) => {
      const t = (el.textContent || '').trim().toLowerCase();
      return t.includes('sign up') || t.includes('continue with') || el.className.includes('primary') || el.className.includes('btn-primary');
    });
    for (const el of candidates) {
      const style = window.getComputedStyle(el as Element);
      if (style.display === 'none' || style.visibility === 'hidden') continue;
      const bg = style.backgroundColor;
      const color = style.color;
      if (bg === color) return true;
      if (bg.includes('0, 0, 0, 0')) continue;
    }
    return false;
  });
}

/** Meaningful nav check: desktop shows link row; mobile shows hamburger — not tiny/collapsed by accident. */
async function navStateBroken(page: Page): Promise<boolean> {
  return page.evaluate(
    ({ desk, mobile }) => {
      const w = window.innerWidth;
      const burger = document.querySelector('[data-mobile-nav-toggle]');
      const desktopStrip = document.querySelector('.ota-nav-links-desktop');
      const nav = document.querySelector('[data-public-nav]');

      if (!nav) return true;

      if (w >= desk) {
        if (!desktopStrip) return true;
        const s = window.getComputedStyle(desktopStrip);
        if (s.display === 'none') return true;
        const r = desktopStrip.getBoundingClientRect();
        if (r.width < 120 || r.height < 16) return true;
        if (burger) {
          const bs = window.getComputedStyle(burger as Element);
          if (bs.display !== 'none') return true;
        }
        return false;
      }

      if (w <= mobile) {
        if (!burger) return true;
        const bs = window.getComputedStyle(burger as Element);
        if (bs.display === 'none' || bs.visibility === 'hidden') return true;
        const br = (burger as HTMLElement).getBoundingClientRect();
        if (br.width < 16 || br.height < 16) return true;
        return false;
      }

      return false;
    },
    { desk: desktopNavMinWidth, mobile: mobileNavMaxWidth },
  );
}

async function ensureMobileMenuCycle(page: Page): Promise<void> {
  const toggle = page.locator('#ota-nav-open').first();
  const burger = page.locator('[data-mobile-nav-toggle]').first();
  await expect(burger).toBeVisible();
  await burger.click();
  await expect(toggle).toBeChecked();
  await burger.click();
  await expect(toggle).not.toBeChecked();
}

async function ensureResultsFilterCycle(page: Page): Promise<void> {
  const openBtn = page.locator('[data-mobile-filter-open]').first();
  const drawer = page.locator('[data-filter-drawer]').first();
  const backdrop = page.locator('[data-filter-backdrop]').first();
  if (await openBtn.count()) {
    await openBtn.click();
    await expect(drawer).toBeVisible();
    await page.locator('[data-filter-close], .ota-filter-close-btn, [data-filter-drawer] .btn:has-text("Close")').first().click({ timeout: 3000 }).catch(async () => {
      await openBtn.click();
    });
    await expect(drawer).toBeHidden();
    if (await backdrop.count()) {
      await expect(backdrop).not.toHaveClass(/is-open/);
    }
  }
}

async function checkAuthInputsContained(page: Page): Promise<void> {
  const bad = await page.evaluate(() => {
    const card = document.querySelector('[data-auth-premium-layout]');
    if (!card) return true;
    const cardRect = card.getBoundingClientRect();
    const pad = 2;
    const inputs = Array.from(card.querySelectorAll('input, select, textarea')) as HTMLElement[];
    return inputs.some((input) => {
      const type = (input.getAttribute('type') || '').toLowerCase();
      if (type === 'hidden') return false;
      const r = input.getBoundingClientRect();
      return r.left < cardRect.left - pad || r.right > cardRect.right + pad;
    });
  });
  expect(bad).toBeFalsy();
}

async function checkRegisterFormContained(page: Page): Promise<void> {
  const bad = await page.evaluate(() => {
    const form = document.querySelector('[data-register-premium-form]');
    if (!form) return true;
    const card = form.closest('[data-auth-premium-layout]');
    if (!card) return true;
    const cardRect = card.getBoundingClientRect();
    const pad = 2;
    const inputs = Array.from(form.querySelectorAll('input, select, textarea')) as HTMLElement[];
    return inputs.some((input) => {
      const type = (input.getAttribute('type') || '').toLowerCase();
      if (type === 'hidden') return false;
      const r = input.getBoundingClientRect();
      return r.left < cardRect.left - pad || r.right > cardRect.right + pad;
    });
  });
  expect(bad).toBeFalsy();
}

async function resultsOrEmptyStateVisible(page: Page): Promise<boolean> {
  return page.evaluate(() => {
    const list = document.querySelector('[data-results-list]');
    if (!list) return false;
    const selectors = [
      '.ota-result-pro-card',
      '.ota-result-card-v2',
      'article',
      '.ota-empty-state-card',
      '.ota-empty-state',
      '[data-empty-filtered-message]',
    ];
    for (const sel of selectors) {
      const els = list.querySelectorAll(sel);
      for (const el of Array.from(els)) {
        const node = el as HTMLElement;
        const r = node.getBoundingClientRect();
        const s = window.getComputedStyle(node);
        if (r.width > 0 && r.height > 0 && s.visibility !== 'hidden' && s.display !== 'none') {
          return true;
        }
      }
    }
    return false;
  });
}

async function ensureVisible(locator: Locator): Promise<void> {
  await expect(locator.first()).toBeVisible({ timeout: 10000 });
}

for (const viewport of viewports) {
  test.describe(`ui visual qa ${viewport.width}x${viewport.height}`, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });

    for (const uiPage of pages) {
      test(`${uiPage.slug} at ${viewport.width}`, async ({ page }) => {
        await page.goto(uiPage.path, { waitUntil: 'load', timeout: 60_000 });
        await page.waitForLoadState('domcontentloaded');

        const screenshotPath = `test-results/ui-qa/${uiPage.slug}-${viewport.width}.png`;
        await page.screenshot({ path: screenshotPath, fullPage: true });

        expect(await hasHorizontalOverflow(page)).toBeFalsy();
        expect(await hasLowContrastPrimaryButtons(page)).toBeFalsy();

        if (!['login', 'register', 'register-agent'].includes(uiPage.slug)) {
          expect(await navStateBroken(page)).toBeFalsy();
        }

        if (viewport.width <= mobileNavMaxWidth && (await page.locator('[data-mobile-nav-toggle]').count())) {
          await ensureMobileMenuCycle(page);
        }

        if (uiPage.slug === 'login') {
          expect(await hasObviousFormOverlap(page)).toBeFalsy();
          await expect(page.locator('[data-auth-premium-layout]')).toBeVisible();
          await expect(page.locator('[data-login-premium-form]')).toBeVisible();
          await ensureVisible(page.locator('input[name="login"], input[name="email"]'));
          await ensureVisible(page.locator('input[name="password"]'));
          await ensureVisible(page.getByRole('link', { name: /continue with google/i }));
          await ensureVisible(page.getByRole('link', { name: /continue with facebook/i }));
          await expect(page.getByText(/Customer Agent Operator/i)).toHaveCount(0);
          await checkAuthInputsContained(page);
          await expect(page.getByRole('link', { name: /customer signup/i }).first()).toBeVisible();
        }

        if (uiPage.slug === 'register') {
          expect(await hasObviousFormOverlap(page)).toBeFalsy();
          await expect(page.locator('[data-register-premium-form]')).toBeVisible();
          await ensureVisible(page.locator('input[name="first_name"]'));
          await ensureVisible(page.locator('[data-error-for="first_name"]'));
          await ensureVisible(page.locator('[data-error-for="email"]'));
          await ensureVisible(page.getByText(/Security check:/i));
          await checkRegisterFormContained(page);
        }

        if (uiPage.slug === 'register-agent') {
          await expect(page.locator('[data-agent-registration-premium]')).toBeVisible();
          await expect(page.locator('input[name="company_name"]')).toBeVisible({ timeout: 10000 });
          expect(await hasObviousFormOverlap(page)).toBeFalsy();
        }

        if (uiPage.slug === 'lookup-booking') {
          await expect(page.locator('[data-lookup-premium-form].ota-form-card')).toBeVisible();
        }

        if (uiPage.slug === 'support') {
          await expect(page.locator('[data-support-premium-form].ota-form-card')).toBeVisible();
        }

        if (uiPage.slug === 'about-us') {
          await expect(page.locator('[data-about-premium]')).toBeVisible();
        }

        if (uiPage.slug === 'flights-results') {
          await expect.poll(async () => resultsOrEmptyStateVisible(page), { timeout: 45_000 }).toBeTruthy();

          const cta = page.getByRole('link', { name: /book now|view fares|flight details/i }).first();
          if (await cta.count()) {
            await expect(cta).toBeEnabled();
          }

          if (viewport.width <= mobileNavMaxWidth) {
            await ensureResultsFilterCycle(page);
          }

          const blockerAfterClose = await page.evaluate(() => {
            const backdrop = document.querySelector('[data-filter-backdrop]') as HTMLElement | null;
            if (!backdrop) return false;
            const style = window.getComputedStyle(backdrop);
            return backdrop.classList.contains('is-open') || (style.pointerEvents !== 'none' && style.display !== 'none');
          });
          expect(blockerAfterClose).toBeFalsy();
        }

        if (uiPage.slug === 'flights-search') {
          await expect(page.locator('[data-flight-search-form], form[action*="flights/results"]')).toBeVisible();
        }
      });
    }
  });
}
