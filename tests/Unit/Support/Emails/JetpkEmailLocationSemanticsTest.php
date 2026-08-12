<?php

namespace Tests\Unit\Support\Emails;

use App\Support\Emails\JetpkEmailBrandingResolver;
use App\Support\Emails\JetpkEmailSampleData;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class JetpkEmailLocationSemanticsTest extends TestCase
{
    #[Test]
    public function seed_branding_defaults_do_not_hardcode_karachi_address(): void
    {
        $method = (new ReflectionClass(JetpkEmailBrandingResolver::class))->getMethod('profileFromSeedDefaults');
        $method->setAccessible(true);
        $profile = $method->invoke(null);

        $this->assertTrue(! array_key_exists('address', $profile) || blank($profile['address'] ?? null));
        $serialized = json_encode($profile) ?: '';
        $this->assertStringNotContainsString('Karachi', $serialized);
    }

    #[Test]
    public function security_notice_sample_does_not_fabricate_city_location(): void
    {
        $helper = new class
        {
            use JetpkEmailSampleData;

            /**
             * @return array<string, mixed>
             */
            public function preview(string $type): array
            {
                return $this->sampleData($type);
            }
        };

        $sample = $helper->preview('security_notice');
        $location = data_get($sample, 'security.location');

        $this->assertTrue($location === null || $location === '');
    }
}
