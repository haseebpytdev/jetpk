<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\JetpkEmailPlainTextRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JetpkEmailPlainTextRendererTest extends TestCase
{
    #[Test]
    public function it_strips_canonical_jetpk_base_layout_style_block_without_css_leak(): void
    {
        $html = view('emails.themes.jetpakistan.layouts.base', [
            'emailBrand' => [
                'brand_name' => 'JetPakistan',
                'primary_color' => '#00843D',
            ],
            'subjectText' => 'Booking update for JP-EMAIL-PLAIN-01',
            'preheaderText' => 'Booking update',
            'headline' => 'Your booking is confirmed',
            'introText' => 'Reference JP-EMAIL-PLAIN-01 is ready for review.',
            'ctaText' => 'View booking',
            'ctaUrl' => 'https://jetpakistan.pk/bookings/JP-EMAIL-PLAIN-01',
            'recipientName' => 'Recovery Customer',
        ])->render();

        $this->assertStringContainsString('<style', $html);
        $this->assertStringContainsString('@media', $html);
        $this->assertStringContainsString('jetpk-container', $html);

        $plain = JetpkEmailPlainTextRenderer::fromHtml($html);

        foreach ([
            '<style',
            '@media',
            '.jetpk-container',
            'jetpk-pad',
            'font-family',
            'mso-table-lspace',
            '-webkit-',
            '-ms-',
            '{ margin:',
            '!important',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $plain, "CSS/style leak found: {$forbidden}");
        }

        $this->assertDoesNotMatchRegularExpression('/<\/?[a-z][^>]*>/i', $plain);
        $this->assertStringContainsString('Your booking is confirmed', $plain);
        $this->assertStringContainsString('JP-EMAIL-PLAIN-01', $plain);
        $this->assertStringContainsString('Reference JP-EMAIL-PLAIN-01 is ready for review.', $plain);
    }
}
