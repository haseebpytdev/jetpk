<?php

namespace Tests\Unit\Services\Visa;

use App\Services\Visa\Support\VisaHostAllowlist;
use App\Services\Visa\VisaPolicyGate;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VisaSecurityTest extends TestCase
{
    public function test_allowlist_blocks_private_and_non_https(): void
    {
        $list = new VisaHostAllowlist(['visa.mofa.gov.sa'], ['/visaservices/searchvisa', '/Base/GetRandomCaptchaImage', '/Home/PrintedUmrahVisa']);

        $this->expectException(\InvalidArgumentException::class);
        $list->assertSafeUrl('http://visa.mofa.gov.sa/visaservices/searchvisa');
    }

    public function test_allowlist_blocks_localhost(): void
    {
        $list = new VisaHostAllowlist(['visa.mofa.gov.sa'], ['/visaservices/searchvisa']);
        $this->expectException(\InvalidArgumentException::class);
        $list->assertSafeUrl('https://127.0.0.1/visaservices/searchvisa');
    }

    public function test_allowlist_accepts_mofa_path(): void
    {
        $list = new VisaHostAllowlist(['visa.mofa.gov.sa'], ['/visaservices/searchvisa', '/Home/PrintedUmrahVisa']);
        $list->assertSafeUrl('https://visa.mofa.gov.sa/visaservices/searchvisa');
        $list->assertRedirectAllowed('/Home/PrintedUmrahVisa', 'visa.mofa.gov.sa');
        $this->assertTrue(true);
    }

    public function test_live_denied_when_policy_false(): void
    {
        Config::set('visa.module_enabled', true);
        Config::set('visa.saudi_mofa.provider_enabled', true);
        Config::set('visa.saudi_mofa.policy_approved', false);
        Config::set('visa.saudi_mofa.transport', 'live');

        $gate = app(VisaPolicyGate::class);
        $this->assertFalse($gate->liveAllowed());
        $this->assertNotNull($gate->denyLiveReason());
    }
}
