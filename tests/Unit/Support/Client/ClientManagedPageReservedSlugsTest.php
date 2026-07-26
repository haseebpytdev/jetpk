<?php

namespace Tests\Unit\Support\Client;

use App\Support\Client\ClientManagedPageReservedSlugs;
use App\Support\Client\ReservedPublicPath;
use Tests\TestCase;

class ClientManagedPageReservedSlugsTest extends TestCase
{
    public function test_reserved_slugs_delegate_to_reserved_public_path(): void
    {
        foreach (['admin', 'payment', 'login', 'checkout', 'lookup-booking'] as $slug) {
            $this->assertTrue(
                ReservedPublicPath::isReservedFirstSegment($slug),
                "Expected ReservedPublicPath to reserve {$slug}"
            );
            $this->assertTrue(
                ClientManagedPageReservedSlugs::isReserved($slug),
                "Expected ClientManagedPageReservedSlugs to reserve {$slug}"
            );
        }
    }

    public function test_custom_page_slug_format_remains_strict(): void
    {
        $this->assertTrue(ClientManagedPageReservedSlugs::isValidFormat('our-story'));
        $this->assertFalse(ClientManagedPageReservedSlugs::isValidFormat('Admin'));
        $this->assertFalse(ClientManagedPageReservedSlugs::isReserved('our-story'));
    }

    public function test_route_slug_constraint_delegates_to_reserved_public_path(): void
    {
        $this->assertSame(
            ReservedPublicPath::customPageSlugConstraint(),
            ClientManagedPageReservedSlugs::routeSlugConstraint(),
        );
    }
}
