<?php

namespace Tests\Unit\Emails;

use App\Support\Emails\EmailRecipientRoleSubjectTagger;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailRecipientRoleSubjectTaggerTest extends TestCase
{
    #[Test]
    public function it_prefixes_admin_agent_and_staff_subjects(): void
    {
        $this->assertSame('[ADMIN] New booking', EmailRecipientRoleSubjectTagger::apply('New booking', 'platform_admin'));
        $this->assertSame('[AGENT] New booking', EmailRecipientRoleSubjectTagger::apply('New booking', 'booking_agent'));
        $this->assertSame('[STAFF] New booking', EmailRecipientRoleSubjectTagger::apply('New booking', 'assigned_staff'));
    }

    #[Test]
    public function it_leaves_customer_subjects_untagged(): void
    {
        $this->assertSame('Booking request received', EmailRecipientRoleSubjectTagger::apply('Booking request received', 'customer'));
        $this->assertSame('Booking request received', EmailRecipientRoleSubjectTagger::apply('Booking request received', null));
    }

    #[Test]
    public function it_does_not_double_prefix(): void
    {
        $this->assertSame('[ADMIN] Already tagged', EmailRecipientRoleSubjectTagger::apply('[ADMIN] Already tagged', 'admin'));
    }

    #[Test]
    public function it_can_retag_from_admin_to_staff(): void
    {
        $this->assertSame(
            '[STAFF] Staff review required',
            EmailRecipientRoleSubjectTagger::retag('[ADMIN] Staff review required', 'assigned_staff'),
        );
    }

    #[Test]
    public function it_maps_buckets_to_role_families(): void
    {
        $this->assertSame('admin', EmailRecipientRoleSubjectTagger::roleForBucket('platform_admin'));
        $this->assertSame('admin', EmailRecipientRoleSubjectTagger::roleForBucket('agency_admin'));
        $this->assertSame('agent', EmailRecipientRoleSubjectTagger::roleForBucket('booking_agent'));
        $this->assertSame('staff', EmailRecipientRoleSubjectTagger::roleForBucket('assigned_staff'));
        $this->assertSame('customer', EmailRecipientRoleSubjectTagger::roleForBucket('booking_customer'));
    }
}
