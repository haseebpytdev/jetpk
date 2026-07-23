<?php

namespace App\Support\Platform;

use InvalidArgumentException;

/**
 * Central catalog of deployable platform modules (registry + deployment presets).
 */
final class PlatformModuleRegistry
{
    /** @var array<string, PlatformModule>|null */
    private static ?array $cache = null;

    /**
     * @return list<PlatformModule>
     */
    public static function all(): array
    {
        return array_values(self::modules());
    }

    public static function find(string $key): ?PlatformModule
    {
        return self::modules()[$key] ?? null;
    }

    /**
     * @return array<string, string> section slug => display title (page order)
     */
    public static function sections(): array
    {
        return [
            'public_website' => 'Public Website',
            'customer_b2c' => 'Customer / B2C',
            'agent_b2b' => 'Agent / B2B',
            'staff_admin' => 'Staff / Admin',
            'finance' => 'Finance',
            'supplier_sabre' => 'Supplier / Sabre',
            'ticketing' => 'Ticketing',
            'support' => 'Support',
            'reports' => 'Reports',
            'notifications' => 'Notifications',
            'settings' => 'Settings',
            'platform_control' => 'Platform / Control',
        ];
    }

    /**
     * Featured deployment presets for Developer CP (display order).
     *
     * @return list<string>
     */
    public static function featuredDeploymentPresetKeys(): array
    {
        return [
            'b2b_b2c',
            'b2b_only',
            'b2c_only',
            'public_search_only',
            'no_supplier_booking',
            'no_ticketing',
            'no_wallet_deposits',
            'maintenance_lite',
        ];
    }

    /**
     * Collapsible module groups for Developer CP UI (Sprint 9C).
     *
     * @return array<string, array{title: string, description: string, registry_sections: list<string>, default_open: bool, protected_only?: bool, exclude_protected?: bool}>
     */
    public static function deploymentUiGroups(): array
    {
        return [
            'core_platform' => [
                'title' => 'Core Platform',
                'description' => 'Core portal gates and platform-wide controls (non-protected).',
                'registry_sections' => ['platform_control'],
                'default_open' => false,
                'exclude_protected' => true,
            ],
            'public_customer' => [
                'title' => 'Public / Customer',
                'description' => 'B2C website, search, checkout, and customer account areas.',
                'registry_sections' => ['public_website', 'customer_b2c'],
                'default_open' => false,
                'exclude_protected' => true,
            ],
            'agent_b2b' => [
                'title' => 'Agent / B2B',
                'description' => 'Agent portal, wallet, staff, and agency workflows.',
                'registry_sections' => ['agent_b2b'],
                'default_open' => false,
                'exclude_protected' => true,
            ],
            'finance_payments' => [
                'title' => 'Finance / Payments',
                'description' => 'Payment proofs, finance reports, and wallet-related flows.',
                'registry_sections' => ['finance'],
                'default_open' => false,
                'exclude_protected' => true,
            ],
            'supplier' => [
                'title' => 'Supplier / Sabre / Duffel',
                'description' => 'Supplier search, booking, GDS/NDC providers, and ticketing.',
                'registry_sections' => ['supplier_sabre', 'ticketing'],
                'default_open' => false,
                'exclude_protected' => true,
            ],
            'admin_operations' => [
                'title' => 'Admin / Operations',
                'description' => 'Staff and admin consoles, settings, and operations.',
                'registry_sections' => ['staff_admin', 'settings'],
                'default_open' => false,
                'exclude_protected' => true,
            ],
            'reports_notifications' => [
                'title' => 'Reports / Notifications',
                'description' => 'Reporting, notifications, and support modules.',
                'registry_sections' => ['reports', 'notifications', 'support'],
                'default_open' => false,
                'exclude_protected' => true,
            ],
            'developer_protected' => [
                'title' => 'Developer / Protected',
                'description' => 'Protected modules that always stay enabled for this deployment.',
                'registry_sections' => [],
                'default_open' => false,
                'protected_only' => true,
            ],
        ];
    }

    /**
     * @return array{requiresAll: list<string>, requiresAny: list<string>}
     */
    public static function dependenciesFor(string $key): array
    {
        $module = self::find($key);

        if ($module === null) {
            return ['requiresAll' => [], 'requiresAny' => []];
        }

        return [
            'requiresAll' => $module->requiresAll,
            'requiresAny' => $module->requiresAny,
        ];
    }

    /**
     * @return list<string>
     */
    public static function dependentsOf(string $key): array
    {
        $dependents = [];

        foreach (self::modules() as $module) {
            if (in_array($key, $module->requiresAll, true) || in_array($key, $module->requiresAny, true)) {
                $dependents[] = $module->key;
            }
        }

        sort($dependents);

        return $dependents;
    }

    /**
     * @param  array<string, bool>  $moduleStates  module key => enabled (simulated future toggles)
     */
    public static function validateDependencies(array $moduleStates): PlatformModuleDependencyValidation
    {
        $violations = [];

        foreach ($moduleStates as $key => $enabled) {
            if (! is_bool($enabled)) {
                $violations[] = [
                    'module' => (string) $key,
                    'code' => 'invalid_state',
                    'message' => 'Module state must be a boolean.',
                ];

                continue;
            }

            $module = self::find((string) $key);
            if ($module === null) {
                $violations[] = [
                    'module' => (string) $key,
                    'code' => 'unknown_module',
                    'message' => 'Unknown module key.',
                ];

                continue;
            }

            if (! $enabled) {
                if ($module->protected) {
                    $violations[] = [
                        'module' => $module->key,
                        'code' => 'protected_module',
                        'message' => "{$module->label} cannot be disabled.",
                    ];
                }

                continue;
            }

            $missingAll = [];
            foreach ($module->requiresAll as $dep) {
                if (! ($moduleStates[$dep] ?? false)) {
                    $missingAll[] = $dep;
                }
            }

            if ($missingAll !== []) {
                $violations[] = [
                    'module' => $module->key,
                    'code' => 'missing_required',
                    'message' => "{$module->label} requires all dependencies to be enabled.",
                    'missing' => $missingAll,
                ];
            }

            if ($module->requiresAny !== []) {
                $anyEnabled = false;
                foreach ($module->requiresAny as $dep) {
                    if ($moduleStates[$dep] ?? false) {
                        $anyEnabled = true;
                        break;
                    }
                }

                if (! $anyEnabled) {
                    $violations[] = [
                        'module' => $module->key,
                        'code' => 'missing_any_of',
                        'message' => "{$module->label} requires at least one dependency from the OR group.",
                        'missing' => $module->requiresAny,
                    ];
                }
            }
        }

        return new PlatformModuleDependencyValidation($violations === [], $violations);
    }

    /**
     * Deployment presets for Developer CP (Sprint 8Q).
     *
     * @return array<string, array{label: string, description: string, modules: array<string, bool>}>
     */
    public static function recommendedProductModes(): array
    {
        $allEnabled = [];
        foreach (array_keys(self::modules()) as $key) {
            $allEnabled[$key] = true;
        }

        $modes = [
            'b2b_only' => [
                'label' => 'B2B only',
                'description' => 'Agent portal, wallet, and admin operations; minimal public B2C surface.',
                'modules' => self::mergeMode($allEnabled, [
                    'public_site' => true,
                    'public_flight_search' => false,
                    'public_umrah_groups' => false,
                    'customer_portal' => false,
                    'customer_registration' => false,
                    'customer_booking_lookup' => false,
                    'customer_checkout' => false,
                    'agent_portal' => true,
                    'agent_staff' => true,
                    'agent_applications' => true,
                    'agent_wallet' => true,
                    'agent_deposits' => true,
                    'agent_ledger' => true,
                    'agent_reports' => true,
                    'agent_support' => true,
                    'saved_travelers' => true,
                    'staff_portal' => true,
                    'admin_portal' => true,
                    'supplier_search' => true,
                    'supplier_booking' => true,
                    'sabre_gds' => true,
                    'payment_proofs' => true,
                    'finance_reports' => true,
                    'support_system' => true,
                    'notifications' => true,
                    'api_settings' => true,
                    'branding_settings' => true,
                    'markup_settings' => true,
                    'platform_module_control' => true,
                ]),
            ],
            'b2c_only' => [
                'label' => 'B2C only',
                'description' => 'Public site, customer portal, and checkout; agent portal off.',
                'modules' => self::mergeMode($allEnabled, [
                    'public_site' => true,
                    'public_flight_search' => true,
                    'public_umrah_groups' => true,
                    'customer_registration' => true,
                    'customer_booking_lookup' => true,
                    'customer_checkout' => true,
                    'agent_portal' => false,
                    'agent_staff' => false,
                    'agent_applications' => false,
                    'agent_wallet' => false,
                    'agent_deposits' => false,
                    'agent_ledger' => false,
                    'agent_reports' => false,
                    'agent_support' => false,
                    'saved_travelers' => true,
                    'supplier_search' => true,
                    'supplier_booking' => true,
                    'payment_proofs' => true,
                    'admin_portal' => true,
                    'staff_portal' => true,
                    'finance_reports' => true,
                    'platform_module_control' => true,
                ]),
            ],
            'b2b_b2c' => [
                'label' => 'Full OTA',
                'description' => 'Full agent and customer portals with shared supplier stack (B2B + B2C).',
                'modules' => $allEnabled,
            ],
            'public_search_only' => [
                'label' => 'Search only',
                'description' => 'Marketing site and flight search without checkout, supplier booking, or ticketing.',
                'modules' => self::mergeMode($allEnabled, [
                    'public_site' => true,
                    'public_flight_search' => true,
                    'public_umrah_groups' => true,
                    'supplier_search' => true,
                    'customer_portal' => false,
                    'customer_registration' => false,
                    'customer_booking_lookup' => false,
                    'customer_checkout' => false,
                    'agent_portal' => false,
                    'agent_staff' => false,
                    'agent_applications' => false,
                    'agent_wallet' => false,
                    'agent_deposits' => false,
                    'agent_ledger' => false,
                    'agent_reports' => false,
                    'agent_support' => false,
                    'saved_travelers' => false,
                    'ticketing' => false,
                    'supplier_booking' => false,
                    'payment_proofs' => false,
                    'admin_portal' => true,
                    'staff_portal' => true,
                    'platform_module_control' => true,
                ]),
            ],
            'no_supplier_booking' => [
                'label' => 'No supplier booking',
                'description' => 'Shopping and checkout may continue; supplier PNR creation and ticketing disabled.',
                'modules' => self::mergeMode($allEnabled, [
                    'supplier_booking' => false,
                    'ticketing' => false,
                ]),
            ],
            'no_ticketing' => [
                'label' => 'No ticketing',
                'description' => 'Supplier booking may continue; automated/staff ticket issuance disabled.',
                'modules' => self::mergeMode($allEnabled, [
                    'ticketing' => false,
                ]),
            ],
            'no_wallet_deposits' => [
                'label' => 'No wallet / deposits',
                'description' => 'Agent portal without wallet balance, deposits, or ledger views.',
                'modules' => self::mergeMode($allEnabled, [
                    'agent_wallet' => false,
                    'agent_deposits' => false,
                    'agent_ledger' => false,
                ]),
            ],
            'maintenance_lite' => [
                'label' => 'Maintenance lite',
                'description' => 'Public site shell and admin access only; portals, search, and supplier flows off.',
                'modules' => self::mergeMode($allEnabled, [
                    'public_site' => true,
                    'public_flight_search' => false,
                    'customer_portal' => false,
                    'customer_registration' => false,
                    'customer_booking_lookup' => false,
                    'customer_checkout' => false,
                    'agent_portal' => false,
                    'agent_staff' => false,
                    'agent_applications' => false,
                    'agent_wallet' => false,
                    'agent_deposits' => false,
                    'agent_ledger' => false,
                    'agent_reports' => false,
                    'agent_support' => false,
                    'saved_travelers' => false,
                    'supplier_search' => false,
                    'supplier_booking' => false,
                    'sabre_gds' => false,
                    'sabre_ndc' => false,
                    'duffel_supplier' => false,
                    'iati_supplier' => false,
                    'pia_ndc_supplier' => false,
                    'one_api_supplier' => false,
                    'airblue_supplier' => false,
                    'ticketing' => false,
                    'payment_proofs' => false,
                    'finance_reports' => false,
                    'support_system' => false,
                    'notifications' => false,
                    'api_settings' => false,
                    'branding_settings' => false,
                    'markup_settings' => false,
                    'admin_portal' => true,
                    'staff_portal' => true,
                    'platform_module_control' => true,
                ]),
            ],
            'agent_portal_only' => [
                'label' => 'Agent portal only',
                'description' => 'Signed-in agents without public customer checkout.',
                'modules' => self::mergeMode($allEnabled, [
                    'public_site' => true,
                    'public_flight_search' => false,
                    'customer_portal' => false,
                    'customer_checkout' => false,
                    'agent_portal' => true,
                    'agent_staff' => true,
                    'agent_wallet' => true,
                    'admin_portal' => true,
                    'platform_module_control' => true,
                ]),
            ],
            'customer_portal_only' => [
                'label' => 'Customer portal only',
                'description' => 'Registered customers; agent portal disabled.',
                'modules' => self::mergeMode($allEnabled, [
                    'customer_portal' => true,
                    'customer_registration' => true,
                    'customer_booking_lookup' => true,
                    'customer_checkout' => true,
                    'agent_portal' => false,
                    'agent_staff' => false,
                    'agent_wallet' => false,
                    'admin_portal' => true,
                    'platform_module_control' => true,
                ]),
            ],
        ];

        foreach ($modes as $presetKey => $mode) {
            $modes[$presetKey]['modules'] = self::finalizePresetModules($mode['modules']);
        }

        return $modes;
    }

    /**
     * @return array<string, bool>
     */
    public static function presetModules(string $presetKey): array
    {
        $modes = self::recommendedProductModes();
        if (! isset($modes[$presetKey])) {
            throw new InvalidArgumentException("Unknown platform module preset: {$presetKey}");
        }

        return $modes[$presetKey]['modules'];
    }

    /**
     * @param  array<string, bool>  $currentStates
     * @return array{enable: list<string>, disable: list<string>}
     */
    public static function presetApplyPreview(string $presetKey, array $currentStates): array
    {
        $target = self::presetModules($presetKey);
        $enable = [];
        $disable = [];

        foreach ($target as $key => $enabled) {
            $current = (bool) ($currentStates[$key] ?? self::find($key)?->defaultEnabled ?? false);
            if ($current === $enabled) {
                continue;
            }

            if ($enabled) {
                $enable[] = $key;
            } else {
                $disable[] = $key;
            }
        }

        sort($enable);
        sort($disable);

        return ['enable' => $enable, 'disable' => $disable];
    }

    /**
     * @param  array<string, bool>  $modules
     * @return array<string, bool>
     */
    public static function finalizePresetModules(array $modules): array
    {
        foreach (self::all() as $module) {
            if ($module->protected) {
                $modules[$module->key] = true;
            }
        }

        if (! ($modules['supplier_search'] ?? false)) {
            $modules['supplier_booking'] = false;
            $modules['ticketing'] = false;
            $modules['sabre_gds'] = false;
            $modules['sabre_ndc'] = false;
            $modules['duffel_supplier'] = false;
            $modules['iati_supplier'] = false;
            $modules['pia_ndc_supplier'] = false;
            $modules['one_api_supplier'] = false;
            $modules['airblue_supplier'] = false;
        }

        if (! ($modules['supplier_booking'] ?? false)) {
            $modules['ticketing'] = false;
        }

        if (! ($modules['agent_portal'] ?? false)) {
            $modules['agent_staff'] = false;
            $modules['agent_wallet'] = false;
            $modules['agent_deposits'] = false;
            $modules['agent_ledger'] = false;
            $modules['agent_reports'] = false;
            $modules['agent_support'] = false;
        }

        if (! ($modules['agent_wallet'] ?? false)) {
            $modules['agent_deposits'] = false;
            $modules['agent_ledger'] = false;
        }

        if (! ($modules['admin_portal'] ?? false)) {
            $modules['finance_reports'] = false;
            $modules['api_settings'] = false;
            $modules['branding_settings'] = false;
            $modules['markup_settings'] = false;
            $modules['platform_module_control'] = true;
        }

        return $modules;
    }

    /**
     * @return array<string, PlatformModule>
     */
    private static function modules(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defs = [
            self::mod(
                'public_site',
                'Public site',
                'Marketing homepage, static pages, and public branding shell.',
                'public_website',
                'low',
                requiresAny: [],
                relatedRoutes: ['home'],
            ),
            self::mod(
                'public_flight_search',
                'Public flight search',
                'Guest and public flight search results (no account required).',
                'public_website',
                'medium',
                requiresAll: ['public_site'],
                relatedRoutes: ['flights.search', 'flights.results'],
            ),
            self::mod(
                'public_umrah_groups',
                'Public Umrah groups',
                'Guest Umrah group package search and detail pages (Al-Haider inventory).',
                'public_website',
                'medium',
                requiresAll: ['public_site'],
                relatedRoutes: ['umrah-groups.index', 'umrah-groups.show'],
            ),
            self::mod(
                'customer_portal',
                'Customer portal',
                'Signed-in customer dashboard, bookings, and profile.',
                'customer_b2c',
                'medium',
                relatedRoutes: ['customer.dashboard', 'customer.bookings.index'],
            ),
            self::mod(
                'customer_registration',
                'Customer registration',
                'Customer sign-up and email verification flows.',
                'customer_b2c',
                'medium',
                requiresAny: ['customer_portal', 'public_site'],
                relatedRoutes: ['register'],
            ),
            self::mod(
                'customer_booking_lookup',
                'Customer booking lookup',
                'Lookup booking by reference without full portal (where enabled).',
                'customer_b2c',
                'low',
                requiresAny: ['public_site', 'customer_portal'],
            ),
            self::mod(
                'customer_checkout',
                'Customer checkout',
                'Public B2C checkout and payment proof upload.',
                'customer_b2c',
                'high',
                requiresAny: ['public_flight_search', 'customer_portal'],
                relatedRoutes: ['bookings.checkout'],
            ),
            self::mod(
                'agent_portal',
                'Agent portal',
                'B2B agent dashboard, bookings, and agency tools.',
                'agent_b2b',
                'high',
                relatedRoutes: ['agent.dashboard'],
            ),
            self::mod(
                'agent_staff',
                'Agent staff',
                'Sub-users under an agent with granular agent permissions.',
                'agent_b2b',
                'high',
                requiresAll: ['agent_portal'],
                relatedRoutes: ['agent.staff.index'],
            ),
            self::mod(
                'agent_applications',
                'Agent applications',
                'New agency/agent onboarding applications (admin review).',
                'agent_b2b',
                'medium',
                relatedRoutes: ['admin.agent-applications.index'],
            ),
            self::mod(
                'agent_wallet',
                'Agent wallet',
                'Agency wallet balance, credits, and spending limits.',
                'agent_b2b',
                'critical',
                requiresAll: ['agent_portal'],
                relatedRoutes: ['agent.wallet'],
            ),
            self::mod(
                'agent_deposits',
                'Agent deposits',
                'Deposit requests and proof approval workflow.',
                'finance',
                'critical',
                requiresAll: ['agent_wallet'],
                relatedRoutes: ['admin.agent-deposits.index', 'agent.deposits.index'],
            ),
            self::mod(
                'agent_ledger',
                'Agent ledger',
                'Read-only wallet/ledger views for agents.',
                'finance',
                'high',
                requiresAll: ['agent_wallet'],
                relatedRoutes: ['agent.ledger.index'],
            ),
            self::mod(
                'agent_reports',
                'Agent reports',
                'Agency-scoped booking and sales reports for agents.',
                'reports',
                'medium',
                requiresAll: ['agent_portal'],
                relatedRoutes: ['agent.reports.index'],
            ),
            self::mod(
                'agent_support',
                'Agent support',
                'Support tickets created from the agent portal.',
                'support',
                'medium',
                requiresAll: ['agent_portal'],
                relatedRoutes: ['agent.support.tickets.index'],
            ),
            self::mod(
                'saved_travelers',
                'Saved travelers',
                'Saved passenger profiles for agents and/or customers.',
                'agent_b2b',
                'low',
                requiresAny: ['agent_portal', 'customer_portal'],
                relatedRoutes: ['agent.travelers.index', 'customer.travelers.index'],
            ),
            self::mod(
                'staff_portal',
                'Staff portal',
                'Operations staff console (bookings, payments, support).',
                'staff_admin',
                'high',
                relatedRoutes: ['staff.dashboard'],
            ),
            self::mod(
                'admin_portal',
                'Admin portal',
                'Platform administrator console (required for operations).',
                'staff_admin',
                'critical',
                protected: true,
                relatedRoutes: ['admin.dashboard'],
                notes: ['Cannot be disabled in future platform module control.'],
            ),
            self::mod(
                'supplier_search',
                'Supplier search',
                'GDS/API flight shopping across configured suppliers.',
                'supplier_sabre',
                'high',
                configHints: ['OTA_SUPPLIER_DEFAULT_PROVIDER', 'OTA_PUBLIC_FLIGHT_RESULTS_SUPPLIERS'],
            ),
            self::mod(
                'supplier_booking',
                'Supplier booking',
                'Create supplier PNR/booking after checkout (live calls gated separately).',
                'supplier_sabre',
                'critical',
                requiresAll: ['supplier_search'],
                configHints: ['SABRE_BOOKING_ENABLED', 'SABRE_BOOKING_LIVE_CALL_ENABLED'],
            ),
            self::mod(
                'sabre_gds',
                'Sabre GDS',
                'Sabre traditional GDS shop and booking integration.',
                'supplier_sabre',
                'critical',
                requiresAll: ['supplier_search'],
                configHints: ['SABRE_BOOKING_ENABLED', 'SABRE_BOOKING_LIVE_CALL_ENABLED', 'SABRE_TICKETING_ENABLED'],
            ),
            self::mod(
                'sabre_ndc',
                'Sabre NDC',
                'Sabre NDC offers and order lifecycle (separate from GDS PNR).',
                'supplier_sabre',
                'high',
                requiresAll: ['supplier_search'],
                notes: ['Controlled by SABRE_NDC_* env flags; no public order create by default.'],
                configHints: ['SABRE_NDC_ENABLED', 'SABRE_NDC_SEARCH_ENABLED', 'SABRE_NDC_ORDER_CREATE_ENABLED'],
            ),
            self::mod(
                'iati_supplier',
                'IATI supplier',
                'IATI Flight API v2 supplier integration.',
                'supplier_sabre',
                'high',
                configHints: ['IATI_TEST_HOST_BASE', 'IATI_PROD_HOST_BASE'],
            ),
            self::mod(
                'pia_ndc_supplier',
                'PIA NDC supplier',
                'PIA Hitit Crane NDC 20.1 supplier integration.',
                'supplier_sabre',
                'high',
                configHints: ['PIA_NDC_TIMEOUT_SECONDS'],
            ),
            self::mod(
                'one_api_supplier',
                'One API supplier',
                'FlyJinnah / Air Arabia hybrid REST search and SOAP book lifecycle.',
                'supplier_sabre',
                'high',
                configHints: ['ONE_API_LIVE_SEARCH_ENABLED', 'ONE_API_LIVE_BOOKING_ENABLED'],
            ),
            self::mod(
                'airblue_supplier',
                'AirBlue supplier',
                'AirBlue Crane NDC 20.1 and Zapways OTA v2.06 integration.',
                'supplier_sabre',
                'high',
                configHints: ['AIRBLUE_TIMEOUT_SECONDS', 'AIRBLUE_OTA_BASE_URL'],
            ),
            self::mod(
                'duffel_supplier',
                'Duffel supplier',
                'Duffel API supplier integration.',
                'supplier_sabre',
                'high',
                configHints: ['DUFFEL_DEFAULT_BASE_URL'],
            ),
            self::mod(
                'ticketing',
                'Ticketing',
                'Automated or staff-assisted ticket issuance (Sabre ticketing heavily gated).',
                'ticketing',
                'critical',
                requiresAll: ['supplier_booking'],
                configHints: ['SABRE_TICKETING_ENABLED'],
                notes: ['Automated Sabre ticketing HTTP remains disabled unless explicitly enabled in env.'],
            ),
            self::mod(
                'payment_proofs',
                'Payment proofs',
                'Manual bank transfer proof upload and verification.',
                'finance',
                'high',
                requiresAny: ['customer_checkout', 'agent_portal'],
                relatedRoutes: ['admin.settings.payments.index'],
            ),
            self::mod(
                'notifications',
                'Notifications',
                'Email/WhatsApp notification routing and delivery logs.',
                'notifications',
                'medium',
                relatedRoutes: ['admin.settings.communications.notification-events.index'],
            ),
            self::mod(
                'finance_reports',
                'Finance reports',
                'Platform finance dashboard, statements, and accounting views.',
                'finance',
                'high',
                requiresAll: ['admin_portal'],
                relatedRoutes: ['admin.finance.dashboard', 'admin.accounting.ledger.index'],
            ),
            self::mod(
                'support_system',
                'Support system',
                'Cross-portal support ticket infrastructure.',
                'support',
                'medium',
                relatedRoutes: ['admin.support.tickets.index'],
            ),
            self::mod(
                'api_settings',
                'API settings',
                'Supplier connection credentials management (secrets never shown on module page).',
                'settings',
                'critical',
                requiresAll: ['admin_portal'],
                relatedRoutes: ['admin.api-settings'],
            ),
            self::mod(
                'branding_settings',
                'Branding settings',
                'Agency branding, homepage, and footer configuration.',
                'settings',
                'medium',
                requiresAll: ['admin_portal'],
                relatedRoutes: ['admin.settings.branding.edit'],
            ),
            self::mod(
                'markup_settings',
                'Markup settings',
                'Pricing markup rules for new bookings.',
                'settings',
                'high',
                requiresAll: ['admin_portal'],
                relatedRoutes: ['admin.markups'],
            ),
            self::mod(
                'platform_module_control',
                'Platform module control',
                'This registry and future deployment module toggles.',
                'settings',
                'critical',
                protected: true,
                requiresAll: ['admin_portal'],
                relatedRoutes: ['dev.cp.modules.index'],
                notes: ['Cannot disable itself in future enforcement.'],
            ),
            self::mod(
                'developer_control_panel',
                'Developer control panel',
                'Product-owner Developer CP (/dev/cp). Access is env + developer_users session, not platform admin.',
                'platform_control',
                'low',
                protected: true,
                relatedRoutes: ['dev.cp.index', 'dev.cp.modules.index'],
                notes: [
                    'Protected in registry only; /dev/cp is not gated by platform_module_settings.',
                ],
            ),
        ];

        $indexed = [];
        foreach ($defs as $module) {
            $indexed[$module->key] = $module;
        }

        self::$cache = $indexed;

        return self::$cache;
    }

    /**
     * @param  list<string>  $requiresAll
     * @param  list<string>  $requiresAny
     * @param  list<string>  $relatedRoutes
     * @param  list<string>  $notes
     * @param  list<string>  $configHints
     */
    private static function mod(
        string $key,
        string $label,
        string $description,
        string $section,
        string $risk,
        bool $defaultEnabled = true,
        bool $protected = false,
        array $requiresAll = [],
        array $requiresAny = [],
        array $relatedRoutes = [],
        array $notes = [],
        array $configHints = [],
    ): PlatformModule {
        return new PlatformModule(
            key: $key,
            label: $label,
            description: $description,
            section: $section,
            risk: $risk,
            defaultEnabled: $defaultEnabled,
            protected: $protected,
            requiresAll: $requiresAll,
            requiresAny: $requiresAny,
            relatedRoutes: $relatedRoutes,
            notes: $notes,
            configHints: $configHints,
        );
    }

    /**
     * @param  array<string, bool>  $base
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private static function mergeMode(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $enabled) {
            $base[$key] = $enabled;
        }

        return $base;
    }
}
