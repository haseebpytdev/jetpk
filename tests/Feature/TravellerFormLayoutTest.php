<?php

namespace Tests\Feature;

use Tests\TestCase;

class TravellerFormLayoutTest extends TestCase
{
    public function test_checkout_passenger_blades_include_gender_and_dob_layout_hooks(): void
    {
        $desktop = (string) file_get_contents(resource_path('views/frontend/booking/partials/passenger-details-body.blade.php'));
        $checkoutCard = (string) file_get_contents(resource_path('views/frontend/checkout/partials/passenger-card.blade.php'));

        $this->assertStringContainsString('ota-pax-field--gender', $desktop);
        $this->assertStringContainsString('ota-pax-field--dob', $desktop);
        $this->assertStringContainsString('ota-pax-field--nationality', $desktop);
        $this->assertStringNotContainsString('ota-pax-field--nationality js-pax-passport-fields', $desktop);
        $this->assertStringContainsString('ota-pax-doc-type-switch', $desktop);
        $this->assertStringContainsString('ota-pax-document-type-choice', $desktop);
        $this->assertStringContainsString('ota-pax-field--gender', $checkoutCard);
        $this->assertStringContainsString('ota-pax-field--dob', $checkoutCard);
    }
}
