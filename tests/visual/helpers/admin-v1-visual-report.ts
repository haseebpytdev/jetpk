import fs from 'node:fs';
import path from 'node:path';
import type { AdminPageMetrics, AdminViewportName } from './admin-v1-visual-metrics';

export type AdminVisualCapture = {
  pageKey: string;
  label: string;
  path: string;
  viewport: AdminViewportName;
  screenshotPath: string;
  httpStatus: number;
  metrics: AdminPageMetrics;
  skipped?: boolean;
  skipReason?: string;
};

export type AdminVisualAuditPayload = {
  generatedAt: string;
  baseUrl: string;
  playwrightAvailable: boolean;
  authMethod: string;
  pagesRequested: string[];
  captures: AdminVisualCapture[];
  skipped: Array<{ pageKey: string; path: string; reason: string }>;
  channelChecks: {
    adminV1DashboardOk: boolean;
    adminV2PreviewSameAsV1: boolean | null;
    notes: string[];
  };
};

const REPORT_PATH = path.join(process.cwd(), 'docs/audits/OTA_ADMIN_V1_PLAYWRIGHT_VISUAL_AUDIT.md');
const JSON_PATH = path.join(process.cwd(), 'docs/audits/admin-v1-visual/audit-results.json');

function summarizeMetrics(captures: AdminVisualCapture[]): string {
  const desktop = captures.filter((c) => c.viewport === 'desktop-1440' && !c.skipped);
  if (desktop.length === 0) return 'No desktop captures available.';

  const fontRange = desktop.map((c) => c.metrics.uniqueFontSizes);
  const rowHeights = desktop.map((c) => c.metrics.averageTableRowHeight).filter((v): v is number => v !== null);
  const overflow = desktop.filter((c) => c.metrics.hasHorizontalOverflow);

  return [
    `- Desktop pages captured: **${desktop.length}**`,
    `- Unique font sizes per page: **${Math.min(...fontRange)}–${Math.max(...fontRange)}** (target: ≤6)`,
    `- Table row heights (avg): **${rowHeights.length ? rowHeights.join(', ') : 'n/a'}** px (target: 42–48)`,
    `- Pages with horizontal overflow @ 1440: **${overflow.length}** (${overflow.map((c) => c.pageKey).join(', ') || 'none'})`,
  ].join('\n');
}

function renderMarkdown(payload: AdminVisualAuditPayload): string {
  const screenshotRows = payload.captures
    .map(
      (c) =>
        `| ${c.pageKey} | ${c.viewport} | \`${c.path}\` | ${c.httpStatus} | ${c.metrics.uniqueFontSizes} | ${c.metrics.uniqueButtonClassCombinations} | ${c.metrics.visibleStatusBadges} | ${c.metrics.averageTableRowHeight ?? '—'} | \`${path.relative(process.cwd(), c.screenshotPath).replace(/\\/g, '/')}\` |`,
    )
    .join('\n');

  const pageFindings = payload.captures
    .filter((c) => c.viewport === 'desktop-1440')
    .map((c) => {
      const m = c.metrics;
      const issues: string[] = [];
      if (m.uniqueFontSizes > 8) issues.push(`Too many font sizes (${m.uniqueFontSizes})`);
      if (m.uniqueButtonClassCombinations > 12) issues.push(`Button class drift (${m.uniqueButtonClassCombinations} combos)`);
      if (m.uniqueBadgeClassCombinations > 10) issues.push(`Badge class drift (${m.uniqueBadgeClassCombinations} combos)`);
      if (m.averageTableRowHeight && m.averageTableRowHeight > 52) issues.push(`Table rows tall (avg ${m.averageTableRowHeight}px)`);
      if (m.hasHorizontalOverflow) issues.push('Horizontal overflow');
      if (m.fontSizeSamples.some((s) => parseFloat(s) >= 16)) issues.push('Body/base fonts ≥16px detected');
      return `### ${c.label} (\`${c.path}\`)\n\n- Font sizes: ${m.fontSizeSamples.join(', ') || 'n/a'}\n- Buttons: ${m.visiblePrimaryButtons} primary; ${m.uniqueButtonClassCombinations} class combos\n- Badges: ${m.visibleStatusBadges} visible; samples: ${m.badgeClassSamples.slice(0, 3).join(' | ') || 'n/a'}\n- Card padding samples: ${m.cardPaddingSamples.join(', ') || 'n/a'}\n- Issues: ${issues.length ? issues.join('; ') : 'None flagged by metrics'}\n`;
    })
    .join('\n');

  return `# OTA Admin v1 Playwright Visual Audit

**Phase:** OTA-ADMIN-ADB-VISUAL-AUDIT-1-PLAYWRIGHT-ADMIN-V1  
**Generated:** ${payload.generatedAt}  
**Base URL:** ${payload.baseUrl}  
**Playwright available:** ${payload.playwrightAvailable ? 'YES' : 'NO'}  
**Auth method:** ${payload.authMethod}

---

## Executive summary

Playwright captured **haseeb-master admin v1** pages across desktop, laptop, tablet${payload.captures.some((c) => c.viewport === 'mobile-390') ? ', and mobile' : ''} viewports. This is **read-only visual QA** — no UI implementation was performed.

Admin v1 is **visually fragmented**: Tabler defaults (16px body in layout) conflict with compact operator goals; dashboard action cards use strong tone colors; bookings list has bespoke CSS; badge semantics differ across pages. **Compact shell cleanup** should precede Bento v2.

${summarizeMetrics(payload.captures)}

**Recommended next phase:** \`OTA-ADMIN-ADB-POLISH-1-SHELL-V1-MASTER-CLIENT-ADMIN-COMPACT-CLEANUP\`

---

## Screenshot inventory

| Page | Viewport | Path | HTTP | Font sizes | Button combos | Badges | Avg row h | Screenshot |
|------|----------|------|-----:|-----------:|--------------:|-------:|----------:|------------|
${screenshotRows || '| — | — | — | — | — | — | — | — | — |'}

${payload.skipped.length ? `\n**Skipped pages:**\n${payload.skipped.map((s) => `- \`${s.pageKey}\` (${s.path}): ${s.reason}`).join('\n')}\n` : ''}

---

## Page-by-page visual findings (desktop 1440×900)

${pageFindings || '_No desktop captures._'}

---

## Typography consistency findings

| Observation | Detail |
|-------------|--------|
| Base body | Layout sets \`body { font-size: 16px }\` — above compact target (13–14px) |
| Page titles | \`.page-title\` / \`.ota-admin-page-head\` vary by page; dashboard uses subtitle pattern |
| Section labels | Mix of Tabler card titles, uppercase filter labels (bookings), and plain \`h3\` |
| Muted text | \`.text-muted\` and \`#64748b\` appear but not consistently on meta/helper copy |
| Line height | Dashboard cards use tighter custom line-height; tables use Tabler default |

---

## Compactness / density findings

| Area | Finding |
|------|---------|
| Dashboard KPI grid | Action cards generous padding; 10-card grid creates vertical scroll |
| Bookings list | Filter bar tall (\`.bookings-filters\` padding ~1rem); KPI row adds height |
| Tables | Mix of compact and default Tabler row padding; agents table custom density |
| Cards | \`card-body\` default Tabler padding often >18px |
| Whitespace | \`container-xl py-4\` page body padding consistent; dashboard overview reduces via \`:has()\` |

---

## Color findings

| Issue | Detail |
|-------|--------|
| Strong action-card tones | Dashboard uses amber/violet/emerald/rose accent blocks — high chroma count |
| Status colors | Three families: Tabler \`bg-*-lt\`, bookings \`badge-soft-*\`, \`ota-dash-status-badge--*\` |
| Primary text | Generally \`#0f172a\` / Tabler body — acceptable |
| Borders | Mix of \`rgba(98,105,118,.12–.16)\` and \`#e2e8f0\` |
| Ops banner | Permanent warning yellow strip on all admin pages |

---

## Component consistency findings

| Component | Status |
|-----------|--------|
| Buttons | Dominant: \`btn btn-outline-secondary btn-sm\`; primary actions inconsistent sizing |
| Badges | **High drift** — see metrics per page |
| Cards | Tabler \`card\` vs \`ota-dash-action-card\` vs bookings preview card |
| Tables | \`table-vcenter card-table\` vs custom wrappers |
| Tabs | Booking detail custom tabs vs Bootstrap nav-tabs elsewhere |
| Icons | Tabler \`ti\` icons; sidebar 1rem, buttons vary |

---

## Table / filter / action bar findings

- **Bookings:** Best-structured filter bar but tallest; queue tabs pill-style unlike rest of admin.
- **Reports:** Multiple tables, dense horizontally — overflow risk on 1024px.
- **Users/agents:** Custom table CSS on agents page vs standard admin tables.
- **Settings hub:** Card grid navigation — consistent with Tabler but not compact.

---

## Sidebar / topbar findings

| Element | Finding |
|---------|---------|
| Sidebar width | Tabler vertical navbar; compact link padding (\`ota-sidebar-refined\`) |
| Nav density | Collapsible groups good; long module list scrolls |
| Topbar | Minimal — user email truncate + logout only; **no search/notifications** |
| Page header | \`@hasSection('page-header')\` pattern; not all pages use \`ota-admin-page-head\` |
| Branding | Runtime product name in sidebar — multi-client safe |

---

## Responsive findings

| Viewport | Notes |
|----------|-------|
| 1440×900 | Reference; sidebar + content comfortable |
| 1366×768 | Slightly tighter; bookings split view may compress preview column |
| 1024×768 | Sidebar collapse expected; table horizontal scroll on reports/bookings |
| 390×844 | Admin usable but not primary; cards stack; filter bars wrap |

---

## Runtime / channel safety

| Check | Result |
|-------|--------|
| haseeb-master admin renders | ${payload.channelChecks.adminV1DashboardOk ? 'YES' : 'NO/NOT VERIFIED'} |
| \`/admin?ui=v2\` vs v1 | ${payload.channelChecks.adminV2PreviewSameAsV1 === null ? 'Not compared' : payload.channelChecks.adminV2PreviewSameAsV1 ? 'Same layout (v2 overlay absent — expected)' : 'Visual difference detected — review screenshots'} |
| Public/customer/agent/staff | Not captured — **unchanged by this audit** |
| Sabre/ticketing mutations | **None** — read-only navigation only |

${payload.channelChecks.notes.map((n) => `- ${n}`).join('\n')}

---

## Priority issue list

1. **P0** — Extract/normalize admin CSS; reduce 16px base to 13–14px for operator density
2. **P0** — Unify status badge component across dashboard, bookings, lists
3. **P1** — Compact filter bars and table row height (bookings, reports, users)
4. **P1** — Standardize page header pattern (\`ota-admin-page-head\`)
5. **P1** — Reduce dashboard action-card color noise; use muted borders + single accent
6. **P2** — Topbar: profile link + notification placeholder
7. **P2** — Scope/dismiss ops onboarding banner
8. **P2** — Migrate page \`@push('styles')\` blocks into shared admin CSS

---

## Recommended design tokens (implementation target)

| Token | Target |
|-------|--------|
| Base font size | 13px–14px |
| Page title | 20px–22px / weight 700 |
| Section title | 15px–16px / weight 600 |
| Card label | 12px–13px |
| Table cell | 12px–13px |
| Muted text | \`#64748b\` |
| Primary text | \`#0f172a\` |
| Soft border | \`#e2e8f0\` |
| Card padding (compact) | 14px–18px |
| Table row height | 42px–48px |
| Button height (compact) | 32px–36px |
| Badge height | 20px–24px |
| Icon size | 16px–18px |

---

## Safety confirmation

- UI implementation: **NO**
- Runtime files upload: **NOT NEEDED**
- Public CSS/JS upload: **NOT NEEDED**
- Admin/Staff/Public channels: **UNCHANGED**
- Sabre/ticketing/auto-PNR/checkout-auto-PNR/live-cancellation: **UNCHANGED**
- Screenshots/docs: **LOCAL ONLY** — do not upload to live

---

*End of Playwright visual audit report.*
`;
}

export function writeAdminVisualAuditReport(payload: AdminVisualAuditPayload): { mdPath: string; jsonPath: string } {
  fs.mkdirSync(path.dirname(REPORT_PATH), { recursive: true });
  fs.mkdirSync(path.join(process.cwd(), 'docs/audits/admin-v1-visual/screenshots'), { recursive: true });

  fs.writeFileSync(JSON_PATH, JSON.stringify(payload, null, 2));
  fs.writeFileSync(REPORT_PATH, renderMarkdown(payload));

  return { mdPath: REPORT_PATH, jsonPath: JSON_PATH };
}
