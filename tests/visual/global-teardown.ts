import fs from 'node:fs';
import path from 'node:path';
import { readAuthStatus } from './helpers/auth';
import { buildAuditReport, readPartialResults, writeAuditReports, writeNamedAuditReports } from './helpers/report';
import { ensureAuditDirs, uiTestRoot } from './helpers/screenshots';
import { activeRoleManifests, skippedPagesFromManifests } from './route-manifest';
import type { AuditRole } from './helpers/types';

export default async function globalTeardown(): Promise<void> {
  ensureAuditDirs();

  const baseUrl =
    process.env.LOCAL_OTA_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8000';

  const auth = readAuthStatus();
  const authReady = new Set<AuditRole>(
    auth.filter((a) => a.success).map((a) => a.role).concat(['guest']),
  );

  const pageResults = readPartialResults();
  const manifests = activeRoleManifests();
  const skippedPages = skippedPagesFromManifests(manifests, authReady);

  let laravelEnv: string | undefined;
  const envPath = path.join(process.cwd(), '.env');
  if (fs.existsSync(envPath)) {
    const match = fs.readFileSync(envPath, 'utf8').match(/^APP_ENV=(.+)$/m);
    laravelEnv = match?.[1]?.trim();
  }

  const report = buildAuditReport({
    baseUrl,
    browsers: ['chromium', 'firefox', 'webkit'],
    auth,
    pageResults,
    skippedPages,
    laravelEnv,
  });

  writeAuditReports(report);

  const agentBasename = process.env.OTA_AUDIT_REPORT_BASENAME;
  if (agentBasename) {
    writeNamedAuditReports(report, agentBasename);
  }
}
