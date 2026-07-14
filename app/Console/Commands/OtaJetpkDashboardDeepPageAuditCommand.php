<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\JetpkPortalUiIdentityClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * Read-only inventory of JetPK dashboard deep pages — theme vs legacy fallback.
 */
class OtaJetpkDashboardDeepPageAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-dashboard-deep-page-audit {--client=jetpk : Client slug for theme resolution}';

    protected $description = 'Inventory JetPK admin dashboard pages — themed, shell-wrapped, pending, or summary module';

    /** @var list<array{area:string,logical:string,label:string,pending?:bool}> */
    private array $pages = [
        ['area' => 'admin', 'logical' => 'index', 'label' => 'admin dashboard'],
        ['area' => 'admin', 'logical' => 'bookings', 'label' => 'admin bookings'],
        ['area' => 'admin', 'logical' => 'bookings.show', 'label' => 'admin booking show'],
        ['area' => 'admin', 'logical' => 'settings.index', 'label' => 'admin settings'],
        ['area' => 'admin', 'logical' => 'page-settings.index', 'label' => 'page settings'],
        ['area' => 'admin', 'logical' => 'api-settings.index', 'label' => 'api settings'],
        ['area' => 'admin', 'logical' => 'api-settings.edit', 'label' => 'api settings edit'],
        ['area' => 'admin', 'logical' => 'settings.branding', 'label' => 'settings branding'],
        ['area' => 'admin', 'logical' => 'settings.communications.index', 'label' => 'settings communications'],
        ['area' => 'admin', 'logical' => 'group-ticketing.index', 'label' => 'group ticketing'],
        ['area' => 'admin', 'logical' => 'group-ticketing.tiles.index', 'label' => 'group ticketing tiles'],
        ['area' => 'admin', 'logical' => 'group-ticketing.categories.index', 'label' => 'group ticketing categories'],
        ['area' => 'admin', 'logical' => 'group-ticketing.inventory.index', 'label' => 'group ticketing inventory'],
        ['area' => 'admin', 'logical' => 'users.index', 'label' => 'users'],
        ['area' => 'admin', 'logical' => 'users.show', 'label' => 'user show'],
        ['area' => 'admin', 'logical' => 'agents', 'label' => 'agents', 'pending' => true],
        ['area' => 'admin', 'logical' => 'agencies.index', 'label' => 'agencies'],
        ['area' => 'admin', 'logical' => 'agencies.show', 'label' => 'agency show'],
        ['area' => 'admin', 'logical' => 'support.tickets.index', 'label' => 'support tickets'],
        ['area' => 'admin', 'logical' => 'support.tickets.show', 'label' => 'support ticket show'],
        ['area' => 'admin', 'logical' => 'agent-applications.index', 'label' => 'agent applications'],
        ['area' => 'admin', 'logical' => 'agent-applications.show', 'label' => 'agent application show'],
        ['area' => 'admin', 'logical' => 'markups.index', 'label' => 'markups'],
        ['area' => 'admin', 'logical' => 'reports', 'label' => 'reports', 'pending' => true],
        ['area' => 'admin', 'logical' => 'supplier-diagnostics', 'label' => 'supplier diagnostics'],
        ['area' => 'staff', 'logical' => 'index', 'label' => 'staff dashboard'],
        ['area' => 'staff', 'logical' => 'bookings.index', 'label' => 'staff bookings'],
        ['area' => 'staff', 'logical' => 'bookings.show', 'label' => 'staff booking show'],
        ['area' => 'agent', 'logical' => 'index', 'label' => 'agent dashboard'],
        ['area' => 'agent', 'logical' => 'bookings.index', 'label' => 'agent bookings'],
        ['area' => 'agent', 'logical' => 'bookings.create', 'label' => 'agent booking create'],
        ['area' => 'agent', 'logical' => 'bookings.show', 'label' => 'agent booking show'],
        ['area' => 'customer', 'logical' => 'dashboard', 'label' => 'customer dashboard'],
        ['area' => 'customer', 'logical' => 'bookings.index', 'label' => 'customer bookings'],
        ['area' => 'customer', 'logical' => 'bookings.show', 'label' => 'customer booking show'],
    ];

    public function handle(
        RuntimeViewResolver $resolver,
        ClientProfileResolver $profileResolver,
        CurrentClientContext $clientContext,
    ): int {
        $slug = trim((string) $this->option('client'));
        $profile = $profileResolver->resolveBySlug($slug);
        if ($profile !== null) {
            $clientContext->set($profile);
        }

        $rows = [];
        $shellWrappedUnexpected = 0;
        foreach ($this->pages as $page) {
            $theme = $resolver->themeViewName($page['logical'], $page['area'], $profile);
            $legacy = $resolver->legacyViewName($page['logical'], $page['area']);
            $hasTheme = View::exists($theme);
            $resolved = $resolver->view($page['logical'], $page['area'], $profile);

            $status = 'shell-wrapped';
            if ($hasTheme) {
                $status = ! empty($page['pending']) ? 'summary-module' : 'themed';
            }

            $isDeferred = ! empty($page['pending']);
            $isPortalArea = in_array($page['area'], ['admin', 'staff', 'agent', 'customer'], true);
            if ($isPortalArea && $status === 'shell-wrapped' && ! $isDeferred) {
                $shellWrappedUnexpected++;
            }

            $uiIdentity = JetpkPortalUiIdentityClassifier::classify($page['area'], $resolved, $status);

            $rows[] = [
                $page['label'],
                $status,
                $uiIdentity,
                $resolved,
                $hasTheme ? 'yes' : 'no',
                $resolved === $legacy ? 'legacy' : 'theme',
            ];
        }

        $this->table(['page', 'status', 'ui_identity', 'resolved_view', 'theme_on_disk', 'resolver'], $rows);
        $this->newLine();
        $this->line(sprintf(
            'unexpected shell-wrapped (admin/staff/agent/customer): %d',
            $shellWrappedUnexpected,
        ));
        $this->line('themed = JetPK-owned view under themes/{area}/jetpakistan/');
        $this->line('summary-module = JetPK shell + KPI/table summary; full interactive UI deferred');
        $this->line('shell-wrapped = resolves to dashboard.{area}.* legacy Tabler inner content');

        if ($shellWrappedUnexpected > 0) {
            $this->error('Portal pages still shell-wrapped — upload themed views and client_view() controllers.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
