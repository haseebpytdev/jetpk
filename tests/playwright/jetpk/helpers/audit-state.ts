import fs from 'node:fs';
import path from 'node:path';
import { AUDIT_OUTPUT_DIR, LIVE_BASE_URL, STATE_FILE } from './constants';

export type LeakHit = {
  page: string;
  viewport: string;
  kind: 'text' | 'href' | 'asset' | 'class' | 'console' | 'network';
  pattern: string;
  detail: string;
  severity: 'fail' | 'warn';
};

export type SectionResult = {
  status: 'pass' | 'fail' | 'warn' | 'blocked' | 'skipped';
  notes: string[];
};

export type ScreenshotEntry = {
  name: string;
  path: string;
  viewport: string;
  url: string;
};

export type AuditState = {
  startedAt: string;
  finishedAt?: string;
  baseUrl: string;
  clientUrl: string;
  viewports: string[];
  testedUrls: string[];
  screenshots: ScreenshotEntry[];
  leaks: LeakHit[];
  consoleErrors: string[];
  networkErrors: string[];
  blockers: string[];
  sections: {
    publicPages: SectionResult;
    searchUiParity: SectionResult;
    oneWayFlow: SectionResult;
    returnFlow: SectionResult;
    multiCity: SectionResult;
    brandedFareCarousel: SectionResult;
    layoverTooltip: SectionResult;
    checkoutVisual: SectionResult;
    headerProfileDashboard: SectionResult;
    guestRedirects: SectionResult;
  };
  fail_count: number;
  warning_count: number;
  leak_count: number;
  can_resume_7K: 'yes' | 'no';
};

function defaultSections(): AuditState['sections'] {
  const empty = (): SectionResult => ({ status: 'skipped', notes: [] });
  return {
    publicPages: empty(),
    searchUiParity: empty(),
    oneWayFlow: empty(),
    returnFlow: empty(),
    multiCity: empty(),
    brandedFareCarousel: empty(),
    layoverTooltip: empty(),
    checkoutVisual: empty(),
    headerProfileDashboard: empty(),
    guestRedirects: empty(),
  };
}

export function createAuditState(viewports: string[]): AuditState {
  return {
    startedAt: new Date().toISOString(),
    baseUrl: LIVE_BASE_URL,
    clientUrl: `${LIVE_BASE_URL}/jetpk`,
    viewports,
    testedUrls: [],
    screenshots: [],
    leaks: [],
    consoleErrors: [],
    networkErrors: [],
    blockers: [],
    sections: defaultSections(),
    fail_count: 0,
    warning_count: 0,
    leak_count: 0,
    can_resume_7K: 'no',
  };
}

let state: AuditState | null = null;

export function getAuditState(): AuditState {
  if (!state) {
    const dir = path.join(process.cwd(), AUDIT_OUTPUT_DIR);
    fs.mkdirSync(dir, { recursive: true });
    const file = path.join(process.cwd(), STATE_FILE);
    if (fs.existsSync(file)) {
      state = JSON.parse(fs.readFileSync(file, 'utf8')) as AuditState;
    } else {
      state = createAuditState([]);
    }
  }
  return state;
}

export function setAuditState(next: AuditState): void {
  state = next;
  persistAuditState();
}

export function persistAuditState(): void {
  if (!state) return;
  const file = path.join(process.cwd(), STATE_FILE);
  fs.mkdirSync(path.dirname(file), { recursive: true });
  fs.writeFileSync(file, JSON.stringify(state, null, 2));
}

export function recomputeSummary(s: AuditState): void {
  const failLeaks = s.leaks.filter((l) => l.severity === 'fail');
  const warnLeaks = s.leaks.filter((l) => l.severity === 'warn');
  const sectionFails = Object.values(s.sections).filter((sec) => sec.status === 'fail').length;
  const sectionBlocked = Object.values(s.sections).filter((sec) => sec.status === 'blocked').length;

  s.leak_count = failLeaks.length;
  s.fail_count = failLeaks.length + sectionFails + s.blockers.length;
  s.warning_count = warnLeaks.length + Object.values(s.sections).filter((sec) => sec.status === 'warn').length;

  const criticalSections = [
    s.sections.publicPages,
    s.sections.searchUiParity,
    s.sections.oneWayFlow,
    s.sections.returnFlow,
    s.sections.guestRedirects,
  ];
  const hasCriticalFail = criticalSections.some((sec) => sec.status === 'fail') || failLeaks.length > 0;
  s.can_resume_7K = hasCriticalFail ? 'no' : 'yes';
}
