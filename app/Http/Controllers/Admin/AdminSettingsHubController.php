<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackgroundRemovalSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Central admin settings landing page — grouped hub for JetPK dashboard.
 */
class AdminSettingsHubController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('platform.admin');

        $groups = [
            'Brand & Website' => array_values(array_filter([
                $this->card('Company branding', 'Logo, legal name, palette, and public brand identity.', 'admin.settings.branding.edit'),
                current_client_slug() ? $this->card('Page settings', 'Home, About, Support — draft, preview, publish.', 'admin.page-settings.index') : null,
                $this->card('Media library', 'Upload, filter, and reuse images across the site.', 'admin.settings.media.index'),
                $this->card('Background removal', $this->backgroundRemovalDescription(), 'admin.settings.background-removal.edit', $this->backgroundRemovalBadge()),
                $this->card('Footer', 'Menus, contact, social, legal, and trust badges.', 'admin.settings.branding.footer.edit'),
                $this->card('About Us', 'Structured About page with optional HTML override.', 'admin.settings.branding.about-us.edit'),
                $this->card('CMS pages', 'Policy and info pages at /pages/{slug}.', 'admin.cms-pages.index'),
            ])),
            'Commerce' => array_values(array_filter([
                $this->card('Payment methods', 'Gateways, manual payments, and proof policy.', 'admin.settings.payments.index'),
                $this->card('Promo codes', 'Discount codes and usage limits.', 'admin.promo-codes.index'),
                $this->card('Markup rules', 'Agency pricing markups on new bookings.', 'admin.markups'),
            ])),
            'Communications' => array_values(array_filter([
                $this->card('Communication settings', 'SMTP, sender identity, and test sends.', 'admin.settings.communications.index'),
                $this->card('Notification routing', 'Per-event email audience and overrides.', 'admin.settings.communications.notification-events.index'),
                $this->card('Email templates', 'Registry of system emails with preview and customize.', 'admin.settings.communications.templates.index'),
                $this->card('Delivery log', 'Sent/failed communications audit trail.', 'admin.settings.communications.delivery-log.index'),
            ])),
            'Integrations' => array_values(array_filter([
                $this->card('Supplier API connections', 'Sabre, Duffel, NDC, and other providers.', 'admin.api-settings'),
                $this->card('Supplier diagnostics', 'Readiness and connection health reports.', 'admin.reports.supplier-diagnostics'),
            ])),
            'Operations' => array_values(array_filter([
                $this->card('Group ticketing', 'Homepage tiles, categories, and inventory sync.', 'admin.group-ticketing.index'),
                $this->card('Support tickets', 'Customer and agent support queue.', 'admin.support.tickets.index'),
                $this->card('Homepage featured fares', 'Dynamic cheapest-fare route rules.', 'admin.settings.homepage-featured-fares.index'),
            ])),
        ];

        return view(
            current_client_slug() ? client_view('settings.index', 'admin') : 'dashboard.admin.settings.index',
            ['groups' => $groups],
        );
    }

    /**
     * @return array{title: string, description: string, route: string, badge?: string}|null
     */
    private function card(string $title, string $description, string $route, ?string $badge = null): ?array
    {
        if (! Route::has($route)) {
            return null;
        }

        $card = [
            'title' => $title,
            'description' => $description,
            'route' => $route,
        ];
        if ($badge !== null) {
            $card['badge'] = $badge;
        }

        return $card;
    }

    private function backgroundRemovalDescription(): string
    {
        return 'Logo background removal provider, API key, and processing status.';
    }

    private function backgroundRemovalBadge(): string
    {
        if (! class_exists(BackgroundRemovalSetting::class)) {
            return 'Not configured';
        }

        try {
            $setting = BackgroundRemovalSetting::query()->first();
            if ($setting === null) {
                return 'Disabled';
            }

            return ($setting->is_enabled ?? false) ? 'Enabled' : 'Disabled';
        } catch (\Throwable) {
            return 'Configure';
        }
    }
}
