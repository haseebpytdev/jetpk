import type { Page } from '@playwright/test';

export type AdminViewportName = 'desktop-1440' | 'laptop-1366' | 'tablet-1024' | 'mobile-390';

export type AdminPageMetrics = {
  uniqueFontSizes: number;
  fontSizeSamples: string[];
  uniqueButtonClassCombinations: number;
  buttonClassSamples: string[];
  uniqueBadgeClassCombinations: number;
  badgeClassSamples: string[];
  averageTableRowHeight: number | null;
  tableRowSamples: number[];
  cardPaddingSamples: string[];
  visiblePrimaryButtons: number;
  visibleStatusBadges: number;
  viewportWidth: number;
  viewportHeight: number;
  documentScrollWidth: number;
  hasHorizontalOverflow: boolean;
};

export async function collectAdminPageMetrics(page: Page): Promise<AdminPageMetrics> {
  return page.evaluate(() => {
    const isVisible = (el: Element): boolean => {
      const style = window.getComputedStyle(el);
      if (style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0') {
        return false;
      }
      const rect = el.getBoundingClientRect();
      return rect.width > 0 && rect.height > 0;
    };

    const fontSizes = new Set<string>();
    const buttonClasses = new Set<string>();
    const badgeClasses = new Set<string>();
    const cardPaddingSamples: string[] = [];
    const tableRowHeights: number[] = [];

    for (const el of document.querySelectorAll('body *')) {
      if (!isVisible(el)) continue;
      const style = window.getComputedStyle(el);
      if (style.fontSize) fontSizes.add(style.fontSize);
    }

    for (const btn of document.querySelectorAll('.btn, button.btn, a.btn')) {
      if (!isVisible(btn)) continue;
      buttonClasses.add((btn as HTMLElement).className.trim().replace(/\s+/g, ' '));
    }

    for (const badge of document.querySelectorAll('.badge, [class*="badge"]')) {
      if (!isVisible(badge)) continue;
      const cls = (badge as HTMLElement).className.trim().replace(/\s+/g, ' ');
      if (cls.includes('badge')) badgeClasses.add(cls);
    }

    for (const card of document.querySelectorAll('.card-body, .ota-dash-panel, .ota-kpi-card')) {
      if (!isVisible(card)) continue;
      if (cardPaddingSamples.length >= 5) break;
      const style = window.getComputedStyle(card);
      cardPaddingSamples.push(style.padding);
    }

    for (const row of document.querySelectorAll('table tbody tr')) {
      if (!isVisible(row)) continue;
      const rect = row.getBoundingClientRect();
      if (rect.height > 0) tableRowHeights.push(Math.round(rect.height));
    }

    const primaryButtons = Array.from(document.querySelectorAll('.btn-primary, .btn.btn-primary')).filter(isVisible).length;
    const statusBadges = Array.from(document.querySelectorAll('.badge, .ota-dash-status-badge, [class*="badge-soft"]')).filter(isVisible).length;

    const avgRow =
      tableRowHeights.length > 0
        ? Math.round(tableRowHeights.reduce((a, b) => a + b, 0) / tableRowHeights.length)
        : null;

    const docEl = document.documentElement;

    return {
      uniqueFontSizes: fontSizes.size,
      fontSizeSamples: Array.from(fontSizes).sort().slice(0, 12),
      uniqueButtonClassCombinations: buttonClasses.size,
      buttonClassSamples: Array.from(buttonClasses).slice(0, 8),
      uniqueBadgeClassCombinations: badgeClasses.size,
      badgeClassSamples: Array.from(badgeClasses).slice(0, 8),
      averageTableRowHeight: avgRow,
      tableRowSamples: tableRowHeights.slice(0, 8),
      cardPaddingSamples,
      visiblePrimaryButtons: primaryButtons,
      visibleStatusBadges: statusBadges,
      viewportWidth: window.innerWidth,
      viewportHeight: window.innerHeight,
      documentScrollWidth: docEl.scrollWidth,
      hasHorizontalOverflow: docEl.scrollWidth > window.innerWidth + 1,
    };
  });
}
