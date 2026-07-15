<?php

namespace App\Console\Commands;

use App\Services\Client\ClientProfileResolver;
use App\Services\Client\CurrentClientContext;
use App\Services\Client\RuntimeViewResolver;
use App\Support\Client\JetpkPortalUiIdentityClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;

/**
 * JetPK portal UI identity audit — operational vs public-portal shell alignment.
 */
class OtaJetpkPortalUiIdentityAuditCommand extends Command
{
    protected $signature = 'ota:jetpk-portal-ui-identity-audit {--client=jetpk : Client slug for theme resolution}';

    protected $description = 'Verify JetPK portal pages use the correct shell family (admin/staff operational, agent/customer public-portal)';

    /** @var list<array{area:string,logical:string,label:string,pending?:bool}> */
    private array $pages = [
        ['area' => 'admin', 'logical' => 'index', 'label' => 'admin dashboard'],
        ['area' => 'admin', 'logical' => 'bookings', 'label' => 'admin bookings'],
        ['area' => 'admin', 'logical' => 'bookings.show', 'label' => 'admin booking show'],
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
        $mismatches = 0;
        $shellWrapped = 0;

        foreach ($this->pages as $page) {
            $hasTheme = View::exists($resolver->themeViewName($page['logical'], $page['area'], $profile));
            $resolved = $resolver->view($page['logical'], $page['area'], $profile);

            $pageStatus = 'shell-wrapped';
            if ($hasTheme) {
                $pageStatus = ! empty($page['pending']) ? 'summary-module' : 'themed';
            }

            $identity = JetpkPortalUiIdentityClassifier::classify($page['area'], $resolved, $pageStatus);
            $expected = JetpkPortalUiIdentityClassifier::expectedShell($page['area']);
            $ok = JetpkPortalUiIdentityClassifier::identityMatchesArea($page['area'], $identity);

            if ($identity === 'shell-wrapped') {
                $shellWrapped++;
            }

            if (! $ok && $pageStatus === 'themed') {
                $mismatches++;
            }

            $rows[] = [
                $page['label'],
                $page['area'],
                $identity,
                $expected,
                $ok ? 'yes' : 'no',
                $resolved,
            ];
        }

        $this->table(['page', 'area', 'ui_identity', 'expected', 'ok', 'resolved_view'], $rows);
        $this->newLine();
        $this->line(sprintf('admin/staff operational-shell pages: %d', count(array_filter($rows, fn ($r) => in_array($r[1], ['admin', 'staff'], true)))));
        $this->line(sprintf('agent/customer public-portal-shell pages: %d', count(array_filter($rows, fn ($r) => in_array($r[1], ['agent', 'customer'], true)))));
        $this->line(sprintf('identity mismatches (themed pages): %d', $mismatches));
        $this->line(sprintf('legacy dashboard.* shell-wrapped: %d', $shellWrapped));

        if ($mismatches > 0 || $shellWrapped > 0) {
            $this->error('Portal UI identity misaligned — agent/customer must use public-portal shell; admin/staff must use operational shell.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
