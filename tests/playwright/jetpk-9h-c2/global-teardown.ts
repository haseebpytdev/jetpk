import { execSync } from 'node:child_process';

export default async function globalTeardown(): Promise<void> {
  execSync('php artisan jetpk:playwright-fixtures --restore-bg-removal-settings --restore-branding-logo', {
    cwd: process.cwd(),
    stdio: 'inherit',
  });
}
