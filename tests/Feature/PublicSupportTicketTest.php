<?php

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Enums\OtaNotificationEvent;
use App\Enums\SupportTicketCategory;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\CommunicationLog;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Database\Seeders\OtaFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.turnstile.enabled' => false,
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
        ]);
    }

    public function test_guest_can_submit_public_support_request(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $response = $this->post(route('support.store'), [
            'form_type' => 'support',
            'name' => 'Jane Guest',
            'email' => 'guest@example.com',
            'subject' => 'Payment help',
            'category' => SupportTicketCategory::Payment->value,
            'body' => 'I need help uploading payment proof.',
        ]);

        $response->assertRedirect(route('support.submitted'));

        $ticket = SupportTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->created_by_user_id);
        $this->assertSame('public', $ticket->source);
        $this->assertSame('Jane Guest', $ticket->requester_name);
        $this->assertSame('guest@example.com', $ticket->requester_email);
        $this->assertMatchesRegularExpression('/^S[A-Z2-9]{7}$/', (string) $ticket->ticket_reference);

        $this->assertDatabaseHas('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'user_id' => null,
            'body' => 'I need help uploading payment proof.',
        ]);

        $this->get(route('support.submitted'))
            ->assertOk()
            ->assertSee($ticket->ticket_reference, false)
            ->assertDontSee('admin.', false);
    }

    public function test_submitted_page_redirects_without_session_reference(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->get(route('support.submitted'))
            ->assertRedirect(route('support'));
    }

    public function test_honeypot_blocks_spam_submission(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->post(route('support.store'), [
            'form_type' => 'support',
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'subject' => 'Spam',
            'category' => SupportTicketCategory::Other->value,
            'body' => 'Buy now',
            'website' => 'http://spam.test',
        ])->assertSessionHasErrors();

        $this->assertSame(0, SupportTicket::query()->count());
    }

    public function test_optional_booking_reference_links_ticket(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $booking = Booking::factory()->create([
            'agency_id' => $agency->id,
            'booking_reference' => 'BKG-PUBLIC-001',
        ]);

        $this->post(route('support.store'), [
            'form_type' => 'support',
            'name' => 'Traveler',
            'email' => 'traveler@example.com',
            'subject' => 'Booking status',
            'category' => SupportTicketCategory::Booking->value,
            'body' => 'Where is my booking?',
            'booking_reference' => $booking->booking_reference,
        ])->assertRedirect(route('support.submitted'));

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame($booking->id, $ticket->booking_id);
    }

    public function test_public_submission_triggers_support_ticket_created_notification_log(): void
    {
        $this->seed(OtaFoundationSeeder::class);

        $this->post(route('support.store'), [
            'form_type' => 'support',
            'name' => 'Notify Guest',
            'email' => 'notify-guest@example.com',
            'subject' => 'Need help',
            'category' => SupportTicketCategory::Technical->value,
            'body' => 'App issue',
        ])->assertRedirect(route('support.submitted'));

        $this->assertGreaterThan(
            0,
            CommunicationLog::query()
                ->where('event', OtaNotificationEvent::SupportTicketCreated->value)
                ->count()
        );
    }

    public function test_authenticated_customer_can_submit_without_name_field(): void
    {
        $this->seed(OtaFoundationSeeder::class);
        $agency = Agency::query()->where('slug', 'asif-travels')->firstOrFail();
        $customer = User::factory()->create([
            'account_type' => AccountType::Customer,
            'current_agency_id' => $agency->id,
            'name' => 'Signed In',
            'email' => 'signedin@example.com',
        ]);
        $agency->users()->attach($customer->id, ['role' => 'customer']);

        $this->actingAs($customer)->post(route('support.store'), [
            'form_type' => 'support',
            'email' => 'signedin@example.com',
            'subject' => 'Account help',
            'category' => SupportTicketCategory::Booking->value,
            'body' => 'Question about my account.',
        ])->assertRedirect(route('support.submitted'));

        $ticket = SupportTicket::query()->firstOrFail();
        $this->assertSame($customer->id, $ticket->created_by_user_id);
        $this->assertSame('public', $ticket->source);
        $this->assertNotNull($ticket->ticket_reference);

        $message = SupportTicketMessage::query()->where('support_ticket_id', $ticket->id)->firstOrFail();
        $this->assertSame($customer->id, $message->user_id);
    }
}
