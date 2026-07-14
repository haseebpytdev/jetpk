<?php

namespace App\Console\Commands;

use App\Enums\AccountType;
use App\Models\User;
use App\Services\Developer\DevCpPlatformAdminUserService;
use Illuminate\Console\Command;

/**
 * Bootstrap first platform_admin user for this OTA deployment (SSH only).
 */
class DevcpBootstrapPlatformAdminCommand extends Command
{
    protected $signature = 'devcp:bootstrap-platform-admin
                            {--email= : Platform admin email}
                            {--name= : Platform admin display name}
                            {--agency-name= : Deployment fallback agency name}
                            {--agency-slug= : Deployment fallback agency slug}
                            {--force : Allow when platform admin already exists}';

    protected $description = 'Create first platform_admin for this deployment (SSH only; prints temp password once)';

    public function handle(DevCpPlatformAdminUserService $platformAdmins): int
    {
        $existingAdmin = User::query()
            ->where('account_type', AccountType::PlatformAdmin)
            ->exists();

        if ($existingAdmin && ! $this->option('force')) {
            $this->error('A platform_admin user already exists. Use --force to create another.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) ($this->option('email') ?: 'admin@ota.local')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --email is required.');

            return self::FAILURE;
        }

        $name = trim((string) ($this->option('name') ?: 'Platform Admin'));
        $agencyName = trim((string) ($this->option('agency-name') ?: 'Platform Owner'));
        $agencySlug = trim((string) ($this->option('agency-slug') ?: config('ota.default_agency_slug', 'platform-owner')));

        $result = $platformAdmins->createPlatformAdmin(
            email: $email,
            name: $name,
            agencyName: $agencyName,
            agencySlug: $agencySlug,
        );

        $this->info('Platform Admin created for this deployment.');
        $this->line('Platform admin: '.$result['user']->email);
        $this->line('Deployment fallback agency: '.$result['agency']->name.' ('.$result['agency']->slug.')');

        if ($result['created'] || $this->option('force')) {
            $this->newLine();
            $this->warn('Temporary password (shown once — store securely and change on first login):');
            $this->line($result['tempPassword']);
        } else {
            $this->comment('Existing user updated; temporary password not printed.');
        }

        $this->newLine();
        $this->comment('User must change password on first login (must_change_password=true).');

        return self::SUCCESS;
    }
}
