<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\EmailVariableIdentifierAuditor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailVariableIdentifierAuditorTest extends TestCase
{
    #[Test]
    public function it_passes_on_clean_email_support_tree(): void
    {
        $result = (new EmailVariableIdentifierAuditor)->scan([
            'app/Support/Emails',
            'config',
            'resources/views',
        ]);

        $this->assertTrue($result['pass']);
        $this->assertSame(0, $result['hit_count']);
    }

    #[Test]
    public function it_detects_truncated_agency_nam_typo(): void
    {
        $pattern = EmailVariableIdentifierAuditor::malformedPattern();
        $typo = 'agency_'.'nam';

        $this->assertSame(1, preg_match($pattern, "'".$typo."'"));
        $this->assertSame(1, preg_match($pattern, '{{ '.$typo.' }}'));
        $this->assertSame(0, preg_match($pattern, 'agency_name'));
        $this->assertSame(0, preg_match($pattern, '{{ agency_name }}'));
        $this->assertSame(0, preg_match($pattern, 'agent_name'));
        $this->assertSame(0, preg_match($pattern, "application['agency_name']"));
    }

    #[Test]
    public function it_detects_other_truncated_variable_typos(): void
    {
        $pattern = EmailVariableIdentifierAuditor::malformedPattern();

        foreach ([
            'brand_'.'nam',
            'company_'.'nam',
            'support_'.'emai',
            'support_'.'phon',
        ] as $typo) {
            $this->assertSame(1, preg_match($pattern, $typo), 'Expected typo detection for '.$typo);
        }

        foreach (['brand_name', 'company_name', 'support_email', 'support_phone'] as $valid) {
            $this->assertSame(0, preg_match($pattern, $valid), 'Valid identifier must not match: '.$valid);
        }
    }

    #[Test]
    public function universal_email_audit_includes_variable_identifier_hygiene_gate(): void
    {
        $exitCode = \Illuminate\Support\Facades\Artisan::call('jetpk:universal-email-render-audit');
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('[variable identifier hygiene]', $output);
        $this->assertStringContainsString('fail_count=0', $output);
        $this->assertStringContainsString('unresolved_placeholders=0', $output);
    }
}
