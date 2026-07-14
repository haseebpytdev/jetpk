import type { ViewportName } from './viewports';

export type AuditRole =
  | 'guest'
  | 'customer'
  | 'agent'
  | 'agent_staff_restricted'
  | 'agent_staff_full'
  | 'admin'
  | 'staff';

export type FailureCategory =
  | 'horizontal_overflow'
  | 'dropdown_viewport'
  | 'calendar'
  | 'table_wrapper'
  | 'form_field'
  | 'text_overflow'
  | 'header_footer_overlap'
  | 'modal_action_dropdown'
  | 'landmark'
  | 'clickable_actions'
  | 'cards'
  | 'navigation';

export type Severity = 'Critical' | 'High' | 'Medium' | 'Low';

export type AuditPageDef = {
  key: string;
  path: string;
  label: string;
  highRisk?: boolean;
  requiresAuth?: boolean;
  skipReason?: string;
  interactive?: boolean;
};

export type RoleManifest = {
  role: AuditRole;
  label: string;
  storageState?: string;
  screenshotDir: string;
  pages: AuditPageDef[];
};

export type OverflowElementInfo = {
  selector: string;
  tag: string;
  className: string;
  id: string;
  text: string;
  rect: { left: number; top: number; right: number; bottom: number; width: number; height: number };
  styles: {
    width: string;
    minWidth: string;
    maxWidth: string;
    position: string;
    overflow: string;
    overflowX: string;
  };
};

export type AuditFailure = {
  id: string;
  category: FailureCategory;
  severity: Severity;
  role: AuditRole;
  pageKey: string;
  pagePath: string;
  browser: string;
  viewport: ViewportName;
  selector: string;
  issue: string;
  screenshotPath?: string;
  suggestedFix: string;
  details?: Record<string, unknown>;
};

export type AuditWarning = {
  role: AuditRole;
  pageKey: string;
  browser: string;
  viewport: ViewportName;
  message: string;
};

export type PageAuditResult = {
  role: AuditRole;
  pageKey: string;
  pagePath: string;
  browser: string;
  viewport: ViewportName;
  status: 'passed' | 'failed' | 'skipped' | 'warning';
  checksRun: number;
  checksPassed: number;
  checksFailed: number;
  screenshotPath?: string;
  interactiveScreenshots?: string[];
  failures: AuditFailure[];
  warnings: AuditWarning[];
  skippedReason?: string;
};

export type SkippedPage = {
  role: AuditRole;
  pageKey: string;
  path: string;
  reason: string;
};

export type AuthSetupResult = {
  role: AuditRole;
  success: boolean;
  email?: string;
  storageStatePath?: string;
  error?: string;
};

export type AuditReport = {
  metadata: {
    generatedAt: string;
    baseUrl: string;
    gitCommit?: string;
    gitBranch?: string;
    laravelEnv?: string;
    browsers: string[];
    viewports: ViewportName[];
    screenshotViewports: ViewportName[];
  };
  safety: {
    localOnly: boolean;
    productionUrlUsed: boolean;
    apiConfigChanged: boolean;
    sabrePaymentTicketingActions: boolean;
    productionUpload: boolean;
  };
  auth: AuthSetupResult[];
  roleCoverage: Record<AuditRole, { tested: boolean; reason?: string }>;
  pagesTested: PageAuditResult[];
  skippedPages: SkippedPage[];
  summary: {
    totalChecks: number;
    passed: number;
    failed: number;
    warnings: number;
    skipped: number;
  };
  failures: AuditFailure[];
  failuresByCategory: Record<FailureCategory, AuditFailure[]>;
  screenshots: Array<{
    role: string;
    pageKey: string;
    browser: string;
    viewport: ViewportName;
    path: string;
    kind: 'page' | 'interactive' | 'failure';
  }>;
  recommendations: Array<{
    priority: number;
    title: string;
    filesLikelyAffected: string[];
    riskLevel: 'Low' | 'Medium' | 'High';
    description: string;
  }>;
  conclusion: {
    readyForFixSprint: boolean;
    highestPriorityFixes: string[];
    manualReviewStillNeeded: boolean;
  };
};
