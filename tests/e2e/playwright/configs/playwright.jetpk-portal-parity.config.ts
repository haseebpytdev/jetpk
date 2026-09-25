import { defineConfig } from '@playwright/test';

import path from 'node:path';
export default defineConfig({
  testDir: path.join(process.cwd(), 'tests/proposed-safe-tests'),
  testMatch: /jetpk-portal-parity\.spec\.ts/,
  timeout: 120_000,
  workers: 1,
  use: {
    baseURL: process.env.LOCAL_OTA_URL ?? 'http://127.0.0.1:8000',
    headless: true,
    screenshot: 'off',
  },
});
