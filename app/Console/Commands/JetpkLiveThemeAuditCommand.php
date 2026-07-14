<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Read-only JetPK live theme/layout audit — stylesheet links, layout fallbacks, DevCP shell.
 */
class JetpkLiveThemeAuditCommand extends Command
{
    protected $signature = 'jetpk:live-theme-audit {--client=jetpk : Client slug}';

    protected $description = 'Read-only JetPK theme audit — CSS links, DevCP shell, profile/auth layouts, email templates';

    /** @var list<string> */
    private array $failures = [];

    /** @var list<string> */
    private array $passes = [];

    public function handle(
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        $this->line('JetPK live theme audit (read-only)');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile !== null) {
            $clientContext->set($profile);
        }

        $this->checkDevCpAssets();
        $this->checkLayoutResolution($resolver, $profile);
        $this->checkProfileViews();
        $this->checkForcePasswordView($resolver, $profile);
        $this->checkEmailPackage();

        $this->newLine();
        $this->info(sprintf('pass=%d fail=%d', count($this->passes), count($this->failures)));

        if ($this->failures !== []) {
            foreach ($this->failures as $failure) {
                $this->error('  - '.$failure);
            }

            return self::FAILURE;
        }

        $this->info('JetPK live theme audit passed.');

        return self::SUCCESS;
    }

    private function checkDevCpAssets(): void
    {
        $devcpCss = public_path('css/devcp.css');
        if (! is_file($devcpCss)) {
            $this->recordFail('devcp:missing public/css/devcp.css');

            return;
        }

        $this->recordPass('devcp:public/css/devcp.css present');

        $layout = File::get(resource_path('views/layouts/developer.blade.php'));
        if (str_contains($layout, "asset('css/devcp.css')")) {
            $this->recordPass('devcp:layout links devcp.css');
        } else {
            $this->recordFail('devcp:layout does not link devcp.css');
        }

        if (str_contains($layout, 'vendor/tabler')) {
            $this->recordFail('devcp:layout still references missing vendor/tabler');
        } else {
            $this->recordPass('devcp:layout does not depend on vendor/tabler');
        }
    }

    private function checkLayoutResolution(RuntimeViewResolver $resolver, ?\App\Models\ClientProfile $profile): void
    {
        $checks = [
            ['dashboard', 'admin', 'admin dashboard shell'],
            ['dashboard', 'staff', 'staff dashboard shell'],
            ['customer-account', 'customer', 'customer account shell'],
            ['agent-portal', 'agent', 'agent portal shell'],
            ['app', 'frontend', 'public frontend shell'],
        ];

        foreach ($checks as [$name, $area, $label]) {
            $sample = $resolver->resolveLayoutSample($name, $area, $profile);
            if ($sample['fallback_used']) {
                $this->recordFail('layout:'.$label.' falls back to legacy ('.$sample['legacy_layout_name'].')');
            } else {
                $this->recordPass('layout:'.$label.' => '.$sample['resolved_layout_name']);
            }
        }
    }

    private function checkProfileViews(): void
    {
        foreach ([
            'profile.edit-dashboard' => 'themes.admin.jetpakistan.layouts.dashboard',
            'profile.edit-frontend' => 'themes.customer.jetpakistan.layouts.customer-account',
            'profile.edit-agent' => 'themes.agent.jetpakistan.layouts.agent-portal',
        ] as $view => $expectedParent) {
            $path = resource_path('views/'.str_replace('.', '/', $view).'.blade.php');
            if (! is_file($path)) {
                $this->recordFail('profile:missing '.$view);

                continue;
            }

            $contents = File::get($path);
            if (str_contains($contents, "extends('layouts.dashboard')")
                || str_contains($contents, "extends('layouts.customer-account')")
                || str_contains($contents, "extends('layouts.agent-portal')")) {
                $this->recordFail('profile:'.$view.' still extends legacy Tabler layout');
            } elseif (str_contains($contents, 'client_layout(')) {
                $this->recordPass('profile:'.$view.' uses client_layout()');
            } else {
                $this->recordFail('profile:'.$view.' does not use client_layout()');
            }
        }
    }

    private function checkForcePasswordView(RuntimeViewResolver $resolver, ?\App\Models\ClientProfile $profile): void
    {
        $themeView = $resolver->themeViewName('auth.force-password-change', 'frontend', $profile);
        if (! View::exists($themeView)) {
            $this->recordFail('auth:missing jetpk force-password-change view');

            return;
        }

        try {
            $html = View::make($themeView)->render();
        } catch (\Throwable $e) {
            $this->recordFail('auth:force-password-change render failed: '.$e->getMessage());

            return;
        }
        if (! str_contains($html, 'jp-auth-form') && ! str_contains($html, 'jp-input')) {
            $this->recordFail('auth:force-password-change missing JetPK form classes');
        } else {
            $this->recordPass('auth:force-password-change uses JetPK auth shell');
        }

        if (str_contains($html, 'container py-5') && str_contains($html, 'card shadow-sm')) {
            $this->recordFail('auth:force-password-change still uses bootstrap card shell');
        }
    }

    private function checkEmailPackage(): void
    {
        if (! View::exists('emails.themes.jetpakistan.layouts.base')) {
            $this->recordFail('email:jetpakistan base layout missing');
        } else {
            $this->recordPass('email:jetpakistan base layout exists');
        }

        if (! View::exists('emails.themes.jetpakistan.auth.otp')) {
            $this->recordFail('email:jetpakistan OTP template missing');
        } else {
            $this->recordPass('email:jetpakistan OTP template exists');
        }

        $renderer = File::get(app_path('Support/Emails/AuthEmailRenderer.php'));
        if (str_contains($renderer, 'usesJetpkEmailPackage') && str_contains($renderer, 'renderJetpkEmail')) {
            $this->recordPass('email:AuthEmailRenderer routes JetPK OTP package');
        } else {
            $this->recordFail('email:AuthEmailRenderer missing JetPK OTP routing');
        }
    }

    private function recordPass(string $message): void
    {
        $this->passes[] = $message;
        $this->line('  OK  '.$message);
    }

    private function recordFail(string $message): void
    {
        $this->failures[] = $message;
        $this->line('  FAIL '.$message);
    }
}
