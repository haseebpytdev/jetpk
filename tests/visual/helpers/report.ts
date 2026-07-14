import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import type {
  AuditFailure,
  AuditReport,
  AuthSetupResult,
  FailureCategory,
  PageAuditResult,
  SkippedPage,
} from './types';
import { ALL_VIEWPORTS, SCREENSHOT_VIEWPORTS } from './viewports';
import { uiTestRoot } from './screenshots';

function gitValue(args: string): string | undefined {
  try {
    return execSync(`git ${args}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim() || undefined;
  } catch {
    return undefined;
  }
}

function groupFailures(failures: AuditFailure[]): Record<FailureCategory, AuditFailure[]> {
  const grouped = {} as Record<FailureCategory, AuditFailure[]>;
  for (const failure of failures) {
    grouped[failure.category] = grouped[failure.category] ?? [];
    grouped[failure.category].push(failure);
  }

  return grouped;
}

function dedupeFailures(failures: AuditFailure[]): AuditFailure[] {
  const seen = new Set<string>();
  const out: AuditFailure[] = [];
  for (const f of failures) {
    const key = `${f.role}|${f.pageKey}|${f.browser}|${f.viewport}|${f.category}|${f.selector}|${f.issue}`;
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(f);
  }

  return out;
}

function buildRecommendations(failures: AuditFailure[]): AuditReport['recommendations'] {
  const recs: AuditReport['recommendations'] = [];
  const categories = new Set(failures.map((f) => f.category));

  if (categories.has('horizontal_overflow') || categories.has('table_wrapper')) {
    recs.push({
      priority: 1,
      title: 'Global overflow and table wrapper fixes',
      filesLikelyAffected: ['public/css/ota-public.css', 'resources/views/layouts/frontend.blade.php', 'resources/views/layouts/dashboard.blade.php'],
      riskLevel: 'Medium',
      description: 'Apply overflow-x:clip on shell; ensure all data tables use .ota-r-table-wrap / .ota-account-table-wrap.',
    });
  }

  if (categories.has('dropdown_viewport') || categories.has('calendar')) {
    recs.push({
      priority: 2,
      title: 'Dropdown and calendar stacking',
      filesLikelyAffected: ['public/css/ota-public.css', 'resources/views/components/account-dropdown.blade.php'],
      riskLevel: 'Medium',
      description: 'Raise z-index for dropdowns/calendars; avoid overflow:hidden on picker parent cards.',
    });
  }

  if (categories.has('form_field')) {
    recs.push({
      priority: 3,
      title: 'Responsive form grid collapse',
      filesLikelyAffected: ['public/css/ota-public.css', 'resources/views/profile/**'],
      riskLevel: 'Low',
      description: 'Collapse multi-column forms below 768px; inputs max-width:100%.',
    });
  }

  return recs;
}

export function buildAuditReport(input: {
  baseUrl: string;
  browsers: string[];
  auth: AuthSetupResult[];
  pageResults: PageAuditResult[];
  skippedPages: SkippedPage[];
  laravelEnv?: string;
}): AuditReport {
  const failures = dedupeFailures(input.pageResults.flatMap((p) => p.failures));
  const warnings = input.pageResults.flatMap((p) => p.warnings);

  const summary = {
    totalChecks: input.pageResults.reduce((sum, p) => sum + p.checksRun, 0),
    passed: input.pageResults.reduce((sum, p) => sum + p.checksPassed, 0),
    failed: failures.length,
    warnings: warnings.length,
    skipped: input.skippedPages.length + input.pageResults.filter((p) => p.status === 'skipped').length,
  };

  const roleCoverage = {
    guest: { tested: input.pageResults.some((p) => p.role === 'guest') },
    customer: {
      tested: input.auth.find((a) => a.role === 'customer')?.success ?? false,
      reason: input.auth.find((a) => a.role === 'customer')?.error,
    },
    agent: {
      tested: input.auth.find((a) => a.role === 'agent')?.success ?? false,
      reason: input.auth.find((a) => a.role === 'agent')?.error,
    },
    agent_staff_restricted: {
      tested: input.auth.find((a) => a.role === 'agent_staff_restricted')?.success ?? false,
      reason:
        input.auth.find((a) => a.role === 'agent_staff_restricted')?.error ??
        'No local agent_staff_restricted user — create via agent portal or set OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL',
    },
    agent_staff_full: {
      tested: input.auth.find((a) => a.role === 'agent_staff_full')?.success ?? false,
      reason:
        input.auth.find((a) => a.role === 'agent_staff_full')?.error ??
        'No local agent_staff_full user — create via agent portal or set OTA_AUDIT_AGENT_STAFF_FULL_EMAIL',
    },
    admin: {
      tested: input.auth.find((a) => a.role === 'admin')?.success ?? false,
      reason: input.auth.find((a) => a.role === 'admin')?.error,
    },
    staff: {
      tested: input.auth.find((a) => a.role === 'staff')?.success ?? false,
      reason: input.auth.find((a) => a.role === 'staff')?.error,
    },
  };

  const screenshots = input.pageResults.flatMap((p) => {
    const items: AuditReport['screenshots'] = [];
    if (p.screenshotPath) {
      items.push({
        role: p.role,
        pageKey: p.pageKey,
        browser: p.browser,
        viewport: p.viewport,
        path: p.screenshotPath,
        kind: 'page',
      });
    }
    for (const shot of p.interactiveScreenshots ?? []) {
      items.push({
        role: p.role,
        pageKey: p.pageKey,
        browser: p.browser,
        viewport: p.viewport,
        path: shot,
        kind: 'interactive',
      });
    }
    for (const f of p.failures) {
      if (f.screenshotPath) {
        items.push({
          role: p.role,
          pageKey: p.pageKey,
          browser: p.browser,
          viewport: p.viewport,
          path: f.screenshotPath,
          kind: 'failure',
        });
      }
    }
    return items;
  });

  const highestPriorityFixes = failures
    .filter((f) => f.severity === 'Critical' || f.severity === 'High')
    .slice(0, 8)
    .map((f) => `[${f.severity}] ${f.role}/${f.pageKey} @ ${f.viewport}: ${f.issue}`);

  return {
    metadata: {
      generatedAt: new Date().toISOString(),
      baseUrl: input.baseUrl,
      gitCommit: gitValue('rev-parse --short HEAD'),
      gitBranch: gitValue('rev-parse --abbrev-ref HEAD'),
      laravelEnv: input.laravelEnv,
      browsers: input.browsers,
      viewports: ALL_VIEWPORTS.map((v) => v.name),
      screenshotViewports: SCREENSHOT_VIEWPORTS,
    },
    safety: {
      localOnly: !/binham|haseebasif|production|\.pk\b/i.test(input.baseUrl),
      productionUrlUsed: /binham|haseebasif|production|\.pk\b/i.test(input.baseUrl),
      apiConfigChanged: false,
      sabrePaymentTicketingActions: false,
      productionUpload: false,
    },
    auth: input.auth,
    roleCoverage,
    pagesTested: input.pageResults,
    skippedPages: input.skippedPages,
    summary,
    failures,
    failuresByCategory: groupFailures(failures),
    screenshots,
    recommendations: buildRecommendations(failures),
    conclusion: {
      readyForFixSprint: failures.length > 0,
      highestPriorityFixes,
      manualReviewStillNeeded: true,
    },
  };
}

function renderFailureSection(title: string, failures: AuditFailure[]): string {
  if (failures.length === 0) {
    return `### ${title}\n\nNone detected.\n`;
  }

  const rows = failures
    .map(
      (f) =>
        `- **[${f.severity}]** \`${f.role}/${f.pageKey}\` — ${f.browser} @ ${f.viewport}\n` +
        `  - Selector: \`${f.selector}\`\n` +
        `  - Issue: ${f.issue}\n` +
        `  - Screenshot: ${f.screenshotPath ?? 'n/a'}\n` +
        `  - Suggested fix: ${f.suggestedFix}\n`,
    )
    .join('\n');

  return `### ${title}\n\n${rows}\n`;
}

function renderAuditMarkdown(report: AuditReport): string {
  return `# OTA Responsive Visual Audit Report

Generated: ${report.metadata.generatedAt}

## 1. Audit metadata

| Field | Value |
|-------|-------|
| Local URL | ${report.metadata.baseUrl} |
| Git commit | ${report.metadata.gitCommit ?? 'n/a'} |
| Git branch | ${report.metadata.gitBranch ?? 'n/a'} |
| Laravel env | ${report.metadata.laravelEnv ?? 'n/a'} |
| Browsers | ${report.metadata.browsers.join(', ')} |
| Viewports (checks) | ${report.metadata.viewports.join(', ')} |
| Screenshot viewports | ${report.metadata.screenshotViewports.join(', ')} |

## 2. Safety confirmation

- Local only: **${report.safety.localOnly ? 'YES' : 'NO'}**
- Production URL used: **${report.safety.productionUrlUsed ? 'YES — STOP' : 'NO'}**
- API config changed: **NO**
- Sabre/payment/ticketing actions: **NO**
- Production upload: **NO**

## 3. Role coverage

| Role | Tested | Notes |
|------|--------|-------|
| guest | ${report.roleCoverage.guest.tested ? 'yes' : 'no'} | |
| customer | ${report.roleCoverage.customer.tested ? 'yes' : 'no'} | ${report.roleCoverage.customer.reason ?? ''} |
| agent | ${report.roleCoverage.agent.tested ? 'yes' : 'no'} | ${report.roleCoverage.agent.reason ?? ''} |
| agent_staff_restricted | ${report.roleCoverage.agent_staff_restricted.tested ? 'yes' : 'no'} | ${report.roleCoverage.agent_staff_restricted.reason ?? ''} |
| agent_staff_full | ${report.roleCoverage.agent_staff_full.tested ? 'yes' : 'no'} | ${report.roleCoverage.agent_staff_full.reason ?? ''} |
| admin | ${report.roleCoverage.admin.tested ? 'yes' : 'no'} | ${report.roleCoverage.admin.reason ?? ''} |
| staff | ${report.roleCoverage.staff.tested ? 'yes' : 'no'} | ${report.roleCoverage.staff.reason ?? ''} |

## 4. Page coverage

**Tested page runs:** ${report.pagesTested.length}

**Skipped pages:** ${report.skippedPages.length}

${report.skippedPages.map((s) => `- \`${s.role}/${s.pageKey}\` (${s.path}): ${s.reason}`).join('\n') || 'None'}

## 5. Automated assertion summary

| Metric | Count |
|--------|------:|
| Total checks | ${report.summary.totalChecks} |
| Passed | ${report.summary.passed} |
| Failed | ${report.summary.failed} |
| Warnings | ${report.summary.warnings} |
| Skipped | ${report.summary.skipped} |

## 6. Failures by severity

${renderFailureSection('Critical', report.failures.filter((f) => f.severity === 'Critical'))}
${renderFailureSection('High', report.failures.filter((f) => f.severity === 'High'))}
${renderFailureSection('Medium', report.failures.filter((f) => f.severity === 'Medium'))}
${renderFailureSection('Low', report.failures.filter((f) => f.severity === 'Low'))}

## 7. Failures by category

${renderFailureSection('A. Horizontal overflow failures', report.failuresByCategory.horizontal_overflow ?? [])}
${renderFailureSection('B. Dropdown viewport failures', report.failuresByCategory.dropdown_viewport ?? [])}
${renderFailureSection('C. Calendar/date picker failures', report.failuresByCategory.calendar ?? [])}
${renderFailureSection('D. Table wrapper failures', report.failuresByCategory.table_wrapper ?? [])}
${renderFailureSection('E. Form field failures', report.failuresByCategory.form_field ?? [])}
${renderFailureSection('F. Text/long string failures', report.failuresByCategory.text_overflow ?? [])}
${renderFailureSection('G. Header/footer overlap failures', report.failuresByCategory.header_footer_overlap ?? [])}
${renderFailureSection('H. Modal/action dropdown failures', report.failuresByCategory.modal_action_dropdown ?? [])}
${renderFailureSection('I. Permission / navigation UI', report.failuresByCategory.navigation ?? [])}

## 8. Screenshots index

${report.screenshots.slice(0, 200).map((s) => `- [${s.kind}] \`${s.path}\``).join('\n')}

${report.screenshots.length > 200 ? `\n_…and ${report.screenshots.length - 200} more (see JSON)._` : ''}

## 9. Recommended fix plan

${report.recommendations.map((r) => `${r.priority}. **${r.title}** (${r.riskLevel})\n   - ${r.description}\n   - Files: ${r.filesLikelyAffected.join(', ')}`).join('\n\n') || 'No automated recommendations — review screenshots manually.'}

## 10. Conclusion

- Ready for fix sprint: **${report.conclusion.readyForFixSprint ? 'YES' : 'NO (no failures recorded)'}**
- Manual review still needed: **${report.conclusion.manualReviewStillNeeded ? 'YES' : 'NO'}**

### Highest priority fixes

${report.conclusion.highestPriorityFixes.map((f) => `- ${f}`).join('\n') || '- None recorded'}

---

*Audit-only run — no production files modified.*
`;
}

export function writeAuditReports(report: AuditReport): { mdPath: string; jsonPath: string } {
  const reportsDir = path.join(uiTestRoot(), 'reports');
  fs.mkdirSync(reportsDir, { recursive: true });

  const jsonPath = path.join(reportsDir, 'responsive-visual-audit.json');
  const mdPath = path.join(reportsDir, 'responsive-visual-audit.md');

  fs.writeFileSync(jsonPath, JSON.stringify(report, null, 2));

  fs.writeFileSync(mdPath, renderAuditMarkdown(report));
  fs.copyFileSync(jsonPath, path.join(uiTestRoot(), 'latest', 'responsive-visual-audit.json'));
  fs.copyFileSync(mdPath, path.join(uiTestRoot(), 'latest', 'responsive-visual-audit.md'));

  return { mdPath, jsonPath };
}

export function writeNamedAuditReports(report: AuditReport, basename: string): { mdPath: string; jsonPath: string } {
  const reportsDir = path.join(uiTestRoot(), 'reports');
  fs.mkdirSync(reportsDir, { recursive: true });

  const jsonPath = path.join(reportsDir, `${basename}.json`);
  const mdPath = path.join(reportsDir, `${basename}.md`);

  fs.writeFileSync(jsonPath, JSON.stringify(report, null, 2));
  fs.writeFileSync(mdPath, renderAuditMarkdown(report));

  return { mdPath, jsonPath };
}

export function appendPageResult(result: PageAuditResult): void {
  const partialPath = path.join(uiTestRoot(), 'reports', 'audit-partial.jsonl');
  fs.mkdirSync(path.dirname(partialPath), { recursive: true });
  fs.appendFileSync(partialPath, `${JSON.stringify(result)}\n`);
}

export function readPartialResults(): PageAuditResult[] {
  const partialPath = path.join(uiTestRoot(), 'reports', 'audit-partial.jsonl');
  if (!fs.existsSync(partialPath)) return [];

  return fs
    .readFileSync(partialPath, 'utf8')
    .split('\n')
    .filter(Boolean)
    .map((line) => JSON.parse(line) as PageAuditResult);
}

export function resetPartialResults(): void {
  const partialPath = path.join(uiTestRoot(), 'reports', 'audit-partial.jsonl');
  if (fs.existsSync(partialPath)) {
    fs.unlinkSync(partialPath);
  }
}
