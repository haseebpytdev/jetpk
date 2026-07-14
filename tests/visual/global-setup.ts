import { resetPartialResults } from './helpers/report';
import { ensureAuditDirs } from './helpers/screenshots';

export default async function globalSetup(): Promise<void> {
  ensureAuditDirs();
  resetPartialResults();
}
