import { test, expect } from '@playwright/test';
import {
  activeRoleManifests,
  roleDirFor,
  ROLE_MANIFESTS,
  skippedPagesFromManifests,
} from './route-manifest';
import type { AuditRole } from './helpers/types';

test('route-manifest named exports resolve', () => {
  expect(ROLE_MANIFESTS.length).toBeGreaterThan(0);
  expect(activeRoleManifests().length).toBeGreaterThan(0);
  expect(roleDirFor('guest')).toBe('public');

  const authReady = new Set<AuditRole>(['guest', 'agent']);
  const skipped = skippedPagesFromManifests(activeRoleManifests(), authReady);
  expect(Array.isArray(skipped)).toBe(true);
});
