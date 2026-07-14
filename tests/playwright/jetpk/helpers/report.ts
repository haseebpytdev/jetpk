import fs from 'node:fs';
import path from 'node:path';
import type { AuditState } from './audit-state';
import { AUDIT_OUTPUT_DIR } from './constants';

function sectionStatusTable(sections: AuditState['sections']): string {
  return Object.entries(sections)
    .map(([key, val]) => `| ${key} | ${val.status} | ${val.notes.join('; ') || '—'} |`)
    .join('\n');
}

export function writeFinalReports(state: AuditState): { mdPath: string; jsonPath: string } {
  const dir = path.join(process.cwd(), AUDIT_OUTPUT_DIR);
  fs.mkdirSync(dir, { recursive: true });

  const jsonPath = path.join(dir, 'jetpk-live-playwright-report.json');
  const mdPath = path.join(dir, 'jetpk-live-playwright-report.md');

  fs.writeFileSync(jsonPath, JSON.stringify(state, null, 2));

  const md = `# JetPK Live Playwright Visual Flow Isolation Audit (8D)

Generated: ${state.finishedAt ?? new Date().toISOString()}

## 1. Executive summary

- **Base URL:** ${state.baseUrl}
- **Client URL:** ${state.clientUrl}
- **fail_count:** ${state.fail_count}
- **warning_count:** ${state.warning_count}
- **leak_count:** ${state.leak_count}
- **can_resume_7K:** ${state.can_resume_7K}

## 2. Live base URL tested

${state.baseUrl}

## 3. Timestamp

- Started: ${state.startedAt}
- Finished: ${state.finishedAt ?? 'in progress'}

## 4. Viewport matrix

${state.viewports.map((v) => `- ${v}`).join('\n')}

## 5. Public page matrix

| Section | Status | Notes |
|---------|--------|-------|
${sectionStatusTable({ publicPages: state.sections.publicPages, searchUiParity: state.sections.searchUiParity, oneWayFlow: state.sections.oneWayFlow, returnFlow: state.sections.returnFlow, multiCity: state.sections.multiCity, brandedFareCarousel: state.sections.brandedFareCarousel, layoverTooltip: state.sections.layoverTooltip, checkoutVisual: state.sections.checkoutVisual, headerProfileDashboard: state.sections.headerProfileDashboard, guestRedirects: state.sections.guestRedirects })}

**Tested URLs (${state.testedUrls.length}):**

${state.testedUrls.map((u) => `- ${u}`).join('\n')}

## 6. Search UI parity result

Status: **${state.sections.searchUiParity.status}**

${state.sections.searchUiParity.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 7. One-way flow result

Status: **${state.sections.oneWayFlow.status}**

${state.sections.oneWayFlow.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 8. Return flow result

Status: **${state.sections.returnFlow.status}**

${state.sections.returnFlow.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 9. Multi-city result

Status: **${state.sections.multiCity.status}**

${state.sections.multiCity.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 10. Branded fare carousel result

Status: **${state.sections.brandedFareCarousel.status}**

${state.sections.brandedFareCarousel.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 11. Layover tooltip result

Status: **${state.sections.layoverTooltip.status}**

${state.sections.layoverTooltip.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 12. Checkout visual flow result

Status: **${state.sections.checkoutVisual.status}**

${state.sections.checkoutVisual.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 13. Header/profile/dashboard result

Status: **${state.sections.headerProfileDashboard.status}**

${state.sections.headerProfileDashboard.notes.map((n) => `- ${n}`).join('\n') || 'No notes.'}

## 14. Forbidden leak scan

${state.leaks.length === 0 ? 'No forbidden leaks detected.' : state.leaks.map((l) => `- [${l.severity}] ${l.page} @ ${l.viewport}: ${l.pattern} — ${l.detail}`).join('\n')}

## 15. Console errors

${state.consoleErrors.length === 0 ? 'None captured.' : state.consoleErrors.map((e) => `- ${e}`).join('\n')}

## 16. Network errors

${state.networkErrors.length === 0 ? 'None captured.' : state.networkErrors.map((e) => `- ${e}`).join('\n')}

## 17. Screenshots index

${state.screenshots.map((s) => `- \`${s.path}\` — ${s.name} (${s.viewport})`).join('\n') || 'No screenshots.'}

## 18. Remaining blockers

${state.blockers.length === 0 ? 'None.' : state.blockers.map((b) => `- ${b}`).join('\n')}

## 19. can_resume_7K

**${state.can_resume_7K}**

---

*Read-only live browser UI audit — no supplier booking/payment mutations attempted.*
`;

  fs.writeFileSync(mdPath, md);

  return {
    mdPath: path.relative(process.cwd(), mdPath).replace(/\\/g, '/'),
    jsonPath: path.relative(process.cwd(), jsonPath).replace(/\\/g, '/'),
  };
}
