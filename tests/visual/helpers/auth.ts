import fs from 'node:fs';
import path from 'node:path';
import type { Page } from '@playwright/test';
import type { AuditRole, AuthSetupResult } from './types';

export type RoleCredential = {
  role: AuditRole;
  email: string;
  password: string;
  storagePath: string;
  optional?: boolean;
};

const AUTH_DIR = path.join(process.cwd(), 'UI_test', '.auth');

export function getAuthDir(): string {
  return AUTH_DIR;
}

function auditRoleFilter(): Set<AuditRole> | null {
  const filter = process.env.OTA_AUDIT_ROLES?.split(',').map((s) => s.trim()).filter(Boolean);
  if (!filter?.length) {
    return null;
  }

  return new Set(filter as AuditRole[]);
}

export function roleCredentials(): RoleCredential[] {
  const all: RoleCredential[] = [
    {
      role: 'customer',
      email: process.env.OTA_AUDIT_CUSTOMER_EMAIL ?? 'customer@ota.demo',
      password: process.env.OTA_AUDIT_PASSWORD ?? 'password',
      storagePath: path.join(AUTH_DIR, 'customer.json'),
    },
    {
      role: 'agent',
      email: process.env.OTA_AUDIT_AGENT_EMAIL ?? 'agent@ota.demo',
      password: process.env.OTA_AUDIT_PASSWORD ?? 'password',
      storagePath: path.join(AUTH_DIR, 'agent.json'),
    },
    {
      role: 'agent_staff_restricted',
      email: process.env.OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL ?? '',
      password: process.env.OTA_AUDIT_PASSWORD ?? 'password',
      storagePath: path.join(AUTH_DIR, 'agent_staff_restricted.json'),
      optional: true,
    },
    {
      role: 'agent_staff_full',
      email: process.env.OTA_AUDIT_AGENT_STAFF_FULL_EMAIL ?? '',
      password: process.env.OTA_AUDIT_PASSWORD ?? 'password',
      storagePath: path.join(AUTH_DIR, 'agent_staff_full.json'),
      optional: true,
    },
    {
      role: 'admin',
      email: process.env.OTA_AUDIT_ADMIN_EMAIL ?? 'admin@ota.demo',
      password: process.env.OTA_AUDIT_PASSWORD ?? 'password',
      storagePath: path.join(AUTH_DIR, 'admin.json'),
    },
    {
      role: 'staff',
      email: process.env.OTA_AUDIT_STAFF_EMAIL ?? 'staff@ota.demo',
      password: process.env.OTA_AUDIT_PASSWORD ?? 'password',
      storagePath: path.join(AUTH_DIR, 'staff.json'),
    },
  ];

  const filter = auditRoleFilter();
  if (!filter) {
    return all;
  }

  return all.filter((c) => filter.has(c.role));
}

export async function loginViaUi(page: Page, email: string, password: string): Promise<void> {
  await page.goto('/login', { waitUntil: 'domcontentloaded', timeout: 60_000 });
  await page.locator('input[name="login"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await page.locator('button[type="submit"], .ota-login-submit').first().click({ noWaitAfter: true });
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 45_000 });
  await page.waitForLoadState('domcontentloaded');
}

export async function setupRoleAuth(page: Page, cred: RoleCredential): Promise<AuthSetupResult> {
  if (cred.optional && !cred.email) {
    return {
      role: cred.role,
      success: false,
      error:
        'No local agent_staff user configured. Set OTA_AUDIT_AGENT_STAFF_RESTRICTED_EMAIL / OTA_AUDIT_AGENT_STAFF_FULL_EMAIL after creating test users locally.',
    };
  }

  fs.mkdirSync(AUTH_DIR, { recursive: true });

  try {
    await page.context().clearCookies();
    await loginViaUi(page, cred.email, cred.password);
    await page.context().storageState({ path: cred.storagePath });

    return {
      role: cred.role,
      success: true,
      email: cred.email,
      storageStatePath: cred.storagePath,
    };
  } catch (error) {
    return {
      role: cred.role,
      success: false,
      email: cred.email,
      error: error instanceof Error ? error.message : String(error),
    };
  }
}

export function readAuthStatus(): AuthSetupResult[] {
  const statusPath = path.join(AUTH_DIR, 'auth-status.json');
  if (!fs.existsSync(statusPath)) {
    return [];
  }

  try {
    return JSON.parse(fs.readFileSync(statusPath, 'utf8')) as AuthSetupResult[];
  } catch {
    return [];
  }
}

export function writeAuthStatus(results: AuthSetupResult[]): void {
  fs.mkdirSync(AUTH_DIR, { recursive: true });
  fs.writeFileSync(path.join(AUTH_DIR, 'auth-status.json'), JSON.stringify(results, null, 2));
}

export function storageStateForRole(role: AuditRole): string | undefined {
  const cred = roleCredentials().find((c) => c.role === role);
  if (!cred) return undefined;
  if (!fs.existsSync(cred.storagePath)) return undefined;

  const auth = readAuthStatus().find((a) => a.role === role);
  if (auth && !auth.success) return undefined;

  return cred.storagePath;
}
