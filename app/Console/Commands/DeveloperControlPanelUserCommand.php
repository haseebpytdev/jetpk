<?php

namespace App\Console\Commands;

use App\Models\DeveloperUser;
use Illuminate\Console\Command;

/**
 * Create or update a developer_users row for /dev/cp access (SSH / local only).
 */
class DeveloperControlPanelUserCommand extends Command
{
    protected $signature = 'dev-cp:user
                            {email : Developer email address}
                            {--name= : Display name}
                            {--password= : Password (prefer interactive prompt on shared hosting)}';

    protected $description = 'Create or update a Developer Control Panel user (hashed password, never logged)';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        $name = trim((string) $this->option('name'));
        if ($name === '') {
            $this->error('The --name option is required.');

            return self::FAILURE;
        }

        $password = (string) $this->option('password');
        if ($password !== '') {
            $this->warn('Do not keep this command in shell history on shared environments.');
        } else {
            $password = (string) $this->secret('Password');
            $confirm = (string) $this->secret('Confirm password');
            if ($password === '' || $confirm === '') {
                $this->error('Password is required.');

                return self::FAILURE;
            }
            if (! hash_equals($password, $confirm)) {
                $this->error('Passwords do not match.');

                return self::FAILURE;
            }
        }

        if ($password === '') {
            $this->error('Password is required (use --password or interactive prompt).');

            return self::FAILURE;
        }

        $developer = DeveloperUser::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $action = $developer === null ? 'Created' : 'Updated';

        if ($developer === null) {
            $developer = new DeveloperUser;
            $developer->email = $email;
        }

        $developer->forceFill([
            'name' => $name,
            'password' => $password,
            'is_active' => true,
        ])->save();

        $this->info(sprintf('%s developer user: %s (%s)', $action, $developer->name, $developer->email));

        return self::SUCCESS;
    }
}
