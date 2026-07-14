<?php

namespace App\Support\Emails;

use App\Support\Branding\CompanyEmailProfile;

/**
 * @internal
 */
final class CustomerFacingEmailRendered
{
    public function __construct(
        public string $html,
        public string $plainBody,
        public CompanyEmailProfile $profile,
    ) {}
}
