<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\JetpkEmailBrandingLeakageAuditor;
use Tests\TestCase;

class JetpkEmailBrandingLeakageAuditorTest extends TestCase
{
    public function test_denylist_config_path_is_excluded_from_template_scan(): void
    {
        $auditor = new JetpkEmailBrandingLeakageAuditor;

        $this->assertTrue($auditor->isExcludedConfigPath('config/jetpk_operational_email.php'));
        $this->assertTrue($auditor->isExcludedConfigPath('config/jetpk_email.php'));
        $this->assertFalse($auditor->isExcludedConfigPath('resources/views/emails/themes/jetpakistan/universal-event.blade.php'));
    }

    public function test_denylist_files_are_excluded_from_template_scan_scope(): void
    {
        $auditor = new JetpkEmailBrandingLeakageAuditor;

        $this->assertTrue($auditor->isExcludedConfigPath('config/jetpk_operational_email.php'));
        $this->assertNotSame([], $auditor->denylistConfigFragments());
    }

    public function test_rendered_parwaaz_in_email_body_fails_scan(): void
    {
        config([
            'jetpk_operational_email.forbidden_brand_fragments' => ['Parwaaz'],
        ]);

        $auditor = new JetpkEmailBrandingLeakageAuditor;
        $hits = $auditor->scanRenderedContent('<p>Book with Parwaaz today</p>', 'email_body');

        $this->assertCount(1, $hits);
        $this->assertSame('Parwaaz', $hits[0]['fragment']);
    }
}
