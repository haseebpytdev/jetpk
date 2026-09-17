<?php

namespace Tests\Unit\Emails;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * P2 mobile gate: info-row must be stacked LABEL/VALUE in one full-width td
 * (never two-column right-align that Gmail Android squeezes).
 */
class JetpkEmailInfoRowStackedLayoutTest extends TestCase
{
    public function test_info_row_is_single_full_width_stacked_cell(): void
    {
        $html = View::make('emails.themes.jetpakistan.partials.info-row', [
            'label' => 'Request context',
            'value' => 'Web sign-in',
            'emailBrand' => [
                'text_color' => '#0f2435',
                'muted_color' => '#64748b',
            ],
        ])->render();

        $this->assertStringContainsString('Request context', $html);
        $this->assertStringContainsString('Web sign-in', $html);
        $this->assertStringContainsString('width="100%"', $html);
        $this->assertStringContainsString('word-break:break-word', $html);
        $this->assertStringNotContainsString('white-space:nowrap', $html);
        $this->assertStringNotContainsString('align="right"', $html);

        // Exactly one <td> inside the row (stacked, not label|value columns).
        $this->assertSame(1, substr_count($html, '<td'));
    }
}
