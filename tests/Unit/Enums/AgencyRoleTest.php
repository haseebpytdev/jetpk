<?php

namespace Tests\Unit\Enums;

use App\Enums\AgencyRole;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AgencyRoleTest extends TestCase
{
    public function test_values_match_expected_business_roles(): void
    {
        $this->assertSame([
            'owner',
            'manager',
            'accountant',
            'sales_agent',
            'support_staff',
            'ticketing_staff',
            'viewer',
        ], AgencyRole::values());
    }

    #[DataProvider('labelProvider')]
    public function test_label_returns_human_readable_name(AgencyRole $role, string $expected): void
    {
        $this->assertSame($expected, $role->label());
    }

    /**
     * @return array<string, array{0: AgencyRole, 1: string}>
     */
    public static function labelProvider(): array
    {
        return [
            'owner' => [AgencyRole::Owner, 'Owner'],
            'manager' => [AgencyRole::Manager, 'Manager'],
            'accountant' => [AgencyRole::Accountant, 'Accountant'],
            'sales agent' => [AgencyRole::SalesAgent, 'Sales Agent'],
            'support staff' => [AgencyRole::SupportStaff, 'Support Staff'],
            'ticketing staff' => [AgencyRole::TicketingStaff, 'Ticketing Staff'],
            'viewer' => [AgencyRole::Viewer, 'Viewer'],
        ];
    }

    public function test_options_returns_value_label_map(): void
    {
        $options = AgencyRole::options();

        $this->assertSame('Owner', $options['owner']);
        $this->assertSame('Sales Agent', $options['sales_agent']);
        $this->assertCount(count(AgencyRole::cases()), $options);
    }

    public function test_from_nullable_parses_valid_values(): void
    {
        $this->assertSame(AgencyRole::Manager, AgencyRole::fromNullable('manager'));
    }

    public function test_from_nullable_returns_null_for_blank_or_invalid(): void
    {
        $this->assertNull(AgencyRole::fromNullable(null));
        $this->assertNull(AgencyRole::fromNullable(''));
        $this->assertNull(AgencyRole::fromNullable('invalid_role'));
    }

    public function test_is_owner_helper(): void
    {
        $this->assertTrue(AgencyRole::Owner->isOwner());
        $this->assertFalse(AgencyRole::Viewer->isOwner());
    }
}
