import type { FullConfig } from '@playwright/test';
import { execSync } from 'node:child_process';
import authGlobalSetup from '../jetpk-9h-b/global-setup';

export default async function globalSetup(config: FullConfig): Promise<void> {
  execSync('php tests/playwright/jetpk-dashboard/ensure-fixtures.php', { stdio: 'inherit' });
  await authGlobalSetup(config);
}
