<?php

namespace Tests\Feature\Jetpk\Concerns;

use App\Enums\AccountType;
use App\Enums\SupportTicketMessageVisibility;
use App\Enums\SupportTicketStatus;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Booking;
use App\Models\ClientProfile;
use App\Models\ClientProfileModule;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Client\CurrentClientContext;
use App\Support\Agents\AgentPermission;
use App\Support\Client\ClientProfileConfigReader;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Support\Facades\Config;
use Tests\Feature\Agent\Concerns\BuildsAgentPortalScenario;

/**
 * Shared JetPK portal test fixtures for JP-PORTAL-0..4A resolver and theme suites.
 */
trait BuildsJetpkPortalTestFixtures
{
    use BuildsAgentPortalScenario;

    /** @var array<string, mixed>|null */
    private ?array $jetpkAgentScenario = null;

    protected function bootJetpkPortalContext(): void
    {
        Config::set('client_route_parity.enabled', false);
        app(CurrentClientContext::class)->set($this->makeJetpkProfile());
    }

    protected function customerUser(): User
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();

        $customer = User::factory()->customer()->create([
            'current_agency_id' => $agency->id,
            'email_verified_at' => now(),
        ]);

        $agency->users()->attach($customer->id, ['role' => 'customer']);

        return $customer;
    }

    /**
     * @return array{0: User, 1: SupportTicket}
     */
    protected function customerTicket(): array
    {
        $ticket = $this->seededTicket();

        return [User::query()->findOrFail($ticket->created_by_user_id), $ticket];
    }

    protected function seededTicket(): SupportTicket
    {
        $customer = $this->customerUser();
        $booking = Booking::factory()->create([
            'agency_id' => $customer->current_agency_id,
            'customer_id' => $customer->id,
            'booking_reference' => 'BKG-'.fake()->unique()->numberBetween(10000, 99999),
        ]);

        $ticket = SupportTicket::query()->create([
            'agency_id' => $booking->agency_id,
            'booking_id' => $booking->id,
            'created_by_user_id' => $customer->id,
            'subject' => 'Portal support ticket',
            'category' => 'booking',
            'status' => SupportTicketStatus::Open,
            'last_reply_at' => now(),
        ]);

        SupportTicketMessage::query()->create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $customer->id,
            'visibility' => SupportTicketMessageVisibility::CustomerVisible,
            'body' => 'Initial customer message',
        ]);

        return $ticket;
    }

    protected function agentAdminUser(): User
    {
        return $this->agentPortalScenario()['adminA'];
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function agentStaffUser(array $permissions): User
    {
        $scenario = $this->agentPortalScenario();

        if ($permissions === []) {
            return $scenario['staff']['A0'];
        }

        if ($permissions === [AgentPermission::TravelersManage]) {
            return $scenario['staff']['A8'];
        }

        if ($permissions === [AgentPermission::SupportManage]) {
            return $scenario['staff']['A9'];
        }

        if ($permissions === [AgentPermission::StaffManage]) {
            return $scenario['staff']['A10'];
        }

        if ($permissions === [AgentPermission::AgencyView]) {
            return $scenario['staff']['A6'];
        }

        if ($permissions === [AgentPermission::AgencyEdit]) {
            return $scenario['staff']['A7'];
        }

        if ($permissions === [AgentPermission::PaymentsUpload]) {
            return $scenario['staff']['A5'];
        }

        if ($permissions === [AgentPermission::WalletView]) {
            return $scenario['staff']['A3'];
        }

        if ($permissions === [AgentPermission::LedgerView]) {
            return $scenario['staff']['A4'];
        }

        if ($permissions === [AgentPermission::ReportsView]) {
            return $scenario['staff']['A11'];
        }

        $agent = $scenario['agentA'];
        $email = 'portal-staff-'.md5(implode(',', $permissions)).'@alpha-staff.test';

        return $this->createAgentStaffUser($agent, $email, $permissions, 'Portal Staff');
    }

    /**
     * @return array<string, mixed>
     */
    protected function agentPortalScenario(): array
    {
        if ($this->jetpkAgentScenario === null) {
            $this->jetpkAgentScenario = $this->buildAgentPortalScenario();
        }

        return $this->jetpkAgentScenario;
    }

    private function makeJetpkProfile(): ClientProfile
    {
        $profile = ClientProfile::query()->create([
            'name' => 'Jet Pakistan',
            'slug' => 'jetpk',
            'environment' => 'staging',
            'active_frontend_theme' => 'jetpakistan',
            'active_admin_theme' => 'jetpakistan',
            'active_staff_theme' => 'jetpakistan',
            'asset_profile' => 'jetpk-assets',
            'default_locale' => 'en',
            'timezone' => 'Asia/Karachi',
            'currency' => 'PKR',
            'is_master_profile' => false,
            'is_active' => true,
        ]);

        foreach (ClientProfileConfigReader::MODULE_KEYS as $moduleKey) {
            ClientProfileModule::query()->create([
                'client_profile_id' => $profile->id,
                'module_key' => $moduleKey,
                'enabled' => true,
            ]);
        }

        return $profile;
    }
}
