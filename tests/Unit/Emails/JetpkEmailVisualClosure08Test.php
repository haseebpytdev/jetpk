<?php

namespace Tests\Unit\Emails;

use App\Support\Emails\JetpkEmailEventRenderer;
use App\Support\Emails\JetpkEmailSampleDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JetpkEmailVisualClosure08Test extends TestCase
{
    use RefreshDatabase;

    public function test_shared_layout_avoids_fixed_640_width_and_full_width_padded_buttons(): void
    {
        $html = $this->renderEvent('booking_confirmed', 'admin');

        $this->assertStringNotContainsString('width="640"', $html);
        $this->assertStringContainsString('max-width:640px', $html);
        $this->assertStringContainsString('padding:24px 28px 8px 28px', $html);
        $this->assertStringNotContainsString('width: 100% !important; box-sizing: border-box', $html);
        $this->assertStringNotContainsString('class="jetpk-stack"', $html);
        $this->assertStringContainsString('&#8595;', $html);
        $this->assertGreaterThanOrEqual(1, substr_count($html, 'padding:16px 20px'));
    }

    public function test_agent_application_card_uses_structural_inner_padding(): void
    {
        $html = $this->renderEvent('agent_application_submitted', 'admin');
        $this->assertStringContainsString('APP-2026-4412', $html);
        $this->assertStringContainsString('padding:16px 20px', $html);
        $this->assertStringNotContainsString('width="640"', $html);
    }

    public function test_support_ticket_cta_button_padding_is_on_td_not_anchor_width(): void
    {
        $html = $this->renderEvent('support_ticket_created', 'admin');
        $this->assertStringContainsString('class="jetpk-btn"', $html);
        $this->assertStringContainsString('padding:14px 22px', $html);
        $this->assertDoesNotMatchRegularExpression('/jetpk-btn a \{[^}]*width:\s*100%/', $html);
    }

    private function renderEvent(string $eventKey, string $role): string
    {
        $sample = JetpkEmailSampleDataProvider::forEvent($eventKey);
        $payload = [];
        foreach (['booking', 'itinerary', 'passengers', 'payment', 'agent_application'] as $block) {
            if (isset($sample[$block]) && is_array($sample[$block])) {
                $payload[$block] = $sample[$block];
            }
        }

        return app(JetpkEmailEventRenderer::class)->render(
            eventKey: $eventKey,
            runtimeVariables: array_merge($sample, ['recipient_role' => $role]),
            payload: $payload,
        )->html;
    }
}
