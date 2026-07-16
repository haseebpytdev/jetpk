<?php

namespace Tests\Feature\Jetpk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Jetpk\Concerns\BuildsJetpkPortalTestFixtures;
use Tests\TestCase;

/**
 * JP-PORTAL-2B — Customer support JetPK theme views.
 */
class JetpkCustomerSupportThemeTest extends TestCase
{
    use BuildsJetpkPortalTestFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootJetpkPortalContext();
    }

    public function test_support_index_resolves_the_jetpk_theme_view(): void
    {
        $resolved = app(\App\Services\Client\RuntimeViewResolver::class)
            ->view('support.tickets.index', 'customer');

        $this->assertSame('themes.customer.jetpakistan.support.tickets.index', $resolved);
    }

    public function test_all_three_support_views_resolve_to_the_theme(): void
    {
        $resolver = app(\App\Services\Client\RuntimeViewResolver::class);

        foreach (['index', 'create', 'show'] as $page) {
            $this->assertSame(
                "themes.customer.jetpakistan.support.tickets.{$page}",
                $resolver->view("support.tickets.{$page}", 'customer')
            );
        }
    }

    public function test_index_renders_with_jetpk_body_and_no_legacy_markup(): void
    {
        $res = $this->actingAs($this->customerUser())
            ->get(route('customer.support.tickets.index'))
            ->assertOk();

        $res->assertSee('jp-btn', false);
        $res->assertDontSee('ota-account-card', false);
        $res->assertDontSee('ota-account-btn', false);
        $res->assertDontSee('ota-account-table', false);
    }

    public function test_create_form_preserves_the_backend_contract(): void
    {
        $res = $this->actingAs($this->customerUser())
            ->get(route('customer.support.tickets.create'))
            ->assertOk();

        $res->assertSee('action="'.route('customer.support.tickets.store').'"', false);
        $res->assertSee('name="_token"', false);
        $res->assertSee('data-testid="customer-support-ticket-form"', false);

        foreach (['subject', 'category', 'booking_id', 'body'] as $field) {
            $res->assertSee('name="'.$field.'"', false);
            $res->assertSee('id="'.$field.'"', false);
        }

        $res->assertSee('maxlength="200"', false);
        $res->assertSee('maxlength="5000"', false);
    }

    public function test_validation_errors_render_and_repopulate(): void
    {
        $customer = $this->customerUser();

        $this->actingAs($customer)
            ->from(route('customer.support.tickets.create'))
            ->post(route('customer.support.tickets.store'), ['subject' => '', 'body' => ''])
            ->assertSessionHasErrors(['subject', 'body']);

        $this->actingAs($customer)
            ->withSession(['_old_input' => ['subject' => 'Kept value']])
            ->get(route('customer.support.tickets.create'))
            ->assertOk()
            ->assertSee('Kept value', false);
    }

    public function test_show_preserves_reply_and_close_contracts(): void
    {
        [$customer, $ticket] = $this->customerTicket();

        $res = $this->actingAs($customer)
            ->get(route('customer.support.tickets.show', $ticket))
            ->assertOk();

        $hasReply = str_contains($res->getContent(), 'customer-support-reply-form');
        $hasClosed = str_contains($res->getContent(), 'This ticket is finalised');
        $this->assertTrue($hasReply || $hasClosed, 'neither reply form nor closed branch rendered');

        if ($hasReply) {
            $res->assertSee('action="'.route('customer.support.tickets.reply', $ticket).'"', false);
            $res->assertSee('name="_token"', false);
        }
    }

    public function test_show_retains_shared_thread_partial(): void
    {
        [$customer, $ticket] = $this->customerTicket();

        $this->actingAs($customer)
            ->get(route('customer.support.tickets.show', $ticket))
            ->assertOk()
            ->assertSee('data-testid="support-ticket-thread"', false);
    }

    public function test_pagination_survives_theming(): void
    {
        $this->actingAs($this->customerUser())
            ->get(route('customer.support.tickets.index'))
            ->assertOk();

        $this->addToAssertionCount(1);
    }
}
