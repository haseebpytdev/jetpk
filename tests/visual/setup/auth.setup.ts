import { test as setup } from '@playwright/test';
import { roleCredentials, setupRoleAuth, writeAuthStatus } from '../helpers/auth';
import { ensureAuditDirs } from '../helpers/screenshots';

setup('prepare auth storage states', async ({ page }) => {
  ensureAuditDirs();

  const results = [];
  for (const cred of roleCredentials()) {
    const result = await setupRoleAuth(page, cred);
    results.push(result);
  }

  writeAuthStatus(results);
});
