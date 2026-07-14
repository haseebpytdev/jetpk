import { getAuditState, persistAuditState, recomputeSummary } from './helpers/audit-state';
import { writeFinalReports } from './helpers/report';

export default async function globalTeardown(): Promise<void> {
  const state = getAuditState();
  state.finishedAt = new Date().toISOString();
  recomputeSummary(state);
  persistAuditState();
  const { mdPath, jsonPath } = writeFinalReports(state);
  console.log(`\nJetPK live audit reports written:\n  ${mdPath}\n  ${jsonPath}\n`);
}
