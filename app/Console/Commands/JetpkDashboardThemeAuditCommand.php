<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\JetpkPortalUiIdentityClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Read-only JetPK dashboard theme/CSS/shell audit across portal areas.
 */
class JetpkDashboardThemeAuditCommand extends Command
{
    protected $signature = 'jetpk:dashboard-theme-audit {--client=jetpk : Client slug}';

    protected $description = 'Audit JetPK dashboard pages for shell, CSS markers, legacy leaks, and form styling (read-only)';

    /** @var list<array{area:string,logical:string,label:string}> */
    private array $pages = [
        ['area' => 'admin', 'logical' => 'index', 'label' => 'admin dashboard'],
        ['area' => 'admin', 'logical' => 'bookings', 'label' => 'admin bookings'],
        ['area' => 'admin', 'logical' => 'bookings.show', 'label' => 'admin booking show'],
        ['area' => 'admin', 'logical' => 'api-settings.index', 'label' => 'supplier list'],
        ['area' => 'admin', 'logical' => 'api-settings.create', 'label' => 'supplier create'],
        ['area' => 'admin', 'logical' => 'api-settings.edit', 'label' => 'supplier edit'],
        ['area' => 'admin', 'logical' => 'page-settings.index', 'label' => 'page settings'],
        ['area' => 'admin', 'logical' => 'page-settings.edit', 'label' => 'page settings edit'],
        ['area' => 'admin', 'logical' => 'settings.branding', 'label' => 'branding'],
        ['area' => 'admin', 'logical' => 'group-ticketing.index', 'label' => 'group ticketing'],
        ['area' => 'admin', 'logical' => 'users.index', 'label' => 'users'],
        ['area' => 'admin', 'logical' => 'markups.index', 'label' => 'markups'],
        ['area' => 'admin', 'logical' => 'support.tickets.index', 'label' => 'support tickets'],
        ['area' => 'staff', 'logical' => 'index', 'label' => 'staff dashboard'],
        ['area' => 'staff', 'logical' => 'bookings.index', 'label' => 'staff bookings'],
        ['area' => 'agent', 'logical' => 'index', 'label' => 'agent dashboard'],
        ['area' => 'agent', 'logical' => 'bookings.index', 'label' => 'agent bookings'],
        ['area' => 'customer', 'logical' => 'dashboard', 'label' => 'customer dashboard'],
        ['area' => 'customer', 'logical' => 'bookings.index', 'label' => 'customer bookings'],
    ];

    /** @var list<string> */
    private array $forbidden = [
        'Parwaaz',
        'YD Travel',
        'YoursDomain',
        'page-pretitle',
        'navbar-vertical',
    ];

    public function handle(
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile === null) {
            $this->error("Client not found: {$slug}");

            return self::FAILURE;
        }
        $clientContext->set($profile);

        $this->line('Classification: READ-ONLY JetPK dashboard theme audit.');
        $this->line('db_write_attempted=false');
        $this->newLine();

        $fail = 0;
        $warn = 0;
        $rows = [];

        $dashCss = public_path('themes/admin/jetpakistan/css/dashboard.css');
        if (! File::exists($dashCss)) {
            $this->error('Missing public/themes/admin/jetpakistan/css/dashboard.css');
            $fail++;
        }

        foreach ($this->pages as $page) {
            $resolved = $resolver->view($page['logical'], $page['area'], $profile);
            $hasTheme = View::exists($resolver->themeViewName($page['logical'], $page['area'], $profile));
            $status = $hasTheme ? 'themed' : 'shell-wrapped';
            $uiIdentity = JetpkPortalUiIdentityClassifier::classify($page['area'], $resolved, $status);

            $sourceIssues = [];
            if (View::exists($resolved)) {
                $source = File::get(resource_path('views/'.str_replace('.', '/', $resolved).'.blade.php'));
                foreach ($this->forbidden as $term) {
                    if (str_contains($source, $term)) {
                        $sourceIssues[] = "forbidden:{$term}";
                    }
                }
                if (str_contains($source, 'form-control') && ! str_contains($source, 'jp-input') && ! str_contains($source, 'jp-module-compat')) {
                    $sourceIssues[] = 'raw-bootstrap-fields';
                }
            } else {
                $sourceIssues[] = 'view-missing';
            }

            $severity = 'pass';
            if (in_array('view-missing', $sourceIssues, true)) {
                $severity = 'fail';
                $fail++;
            } elseif ($sourceIssues !== []) {
                $severity = 'warn';
                $warn++;
            }

            $rows[] = [
                $page['label'],
                $severity,
                $uiIdentity,
                $status,
                $resolved,
                implode(', ', $sourceIssues) ?: '—',
            ];
        }

        $this->table(['page', 'severity', 'ui_identity', 'theme', 'view', 'findings'], $rows);
        $this->newLine();
        $this->line("pass=".max(0, count($rows) - $fail - $warn)." warn={$warn} fail={$fail}");

        return $fail > 0 ? self::FAILURE : self::SUCCESS;
    }
}
