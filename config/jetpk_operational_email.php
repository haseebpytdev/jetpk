<?php

/*
|--------------------------------------------------------------------------
| JetPakistan operational email event registry
|--------------------------------------------------------------------------
|
| Central registry for recipient policies, per-bucket delivery, audience
| variants, and deduplication. Unknown event keys must fail safely in
| JetpkOperationalEmailEventRegistry::assertKnownEvent().
|
*/

return [

  'client_slug' => 'jetpk',

  /*
  | Events that send separate emails per recipient bucket (role-specific copy).
  */
  'per_bucket_delivery' => [
    'agent_application_submitted' => true,
    'agent_application_approved' => true,
    'agent_application_rejected' => true,
    'agent_application_needs_more_info' => true,
    'staff_created' => true,
    'agent_created' => true,
    'user_suspended' => true,
    'user_activated' => true,
  ],

  /*
  | Bucket → variant key for per-bucket delivery (variant content below).
  */
  'bucket_variants' => [
    'agent_application_submitted' => [
      'admin' => 'admin',
      'applicant' => 'applicant',
    ],
    'agent_application_approved' => [
      'admin' => 'admin',
      'applicant' => 'applicant',
    ],
    'agent_application_rejected' => [
      'admin' => 'admin',
      'applicant' => 'applicant',
    ],
    'agent_application_needs_more_info' => [
      'admin' => 'admin',
      'applicant' => 'applicant',
    ],
    'staff_created' => [
      'admin' => 'admin',
      'staff' => 'staff',
    ],
    'agent_created' => [
      'admin' => 'admin',
      'agent' => 'agent',
    ],
    'user_suspended' => [
      'admin' => 'admin',
      'user' => 'user',
    ],
    'user_activated' => [
      'admin' => 'admin',
      'user' => 'user',
    ],
  ],

  /*
  | Audience-variant copy overrides merged into JetPK event content at render time.
  */
  'variants' => [
    'agent_application_submitted' => [
      'applicant' => [
        'subject' => '{{ brand_name }} — We received your agent application',
        'heading' => 'Application received',
        'intro' => 'Thank you for applying to partner with {{ brand_name }}. We have received your application for {{ company_name }} and our team will review it shortly.',
        'status_label' => 'Pending review',
        'status_type' => 'info',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — New agent application received',
        'heading' => 'New agent application',
        'intro' => 'A new agent partnership application has been submitted and requires review.',
        'status_label' => 'Review required',
        'status_type' => 'warning',
      ],
    ],
    'agent_application_approved' => [
      'applicant' => [
        'subject' => '{{ brand_name }} — Your agent application has been approved',
        'heading' => 'Welcome, partner',
        'intro' => 'We are pleased to inform you that your agent application for {{ company_name }} has been approved.',
        'status_label' => 'Approved',
        'status_type' => 'success',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — Agent application approved',
        'heading' => 'Application approved',
        'intro' => 'An agent application has been approved and the agent account is ready.',
        'status_label' => 'Approved',
        'status_type' => 'success',
      ],
    ],
    'agent_application_rejected' => [
      'applicant' => [
        'subject' => '{{ brand_name }} — Update on your agent application',
        'heading' => 'Application update',
        'intro' => 'Thank you for your interest in partnering with {{ brand_name }}. After careful review, we are unable to approve your application for {{ company_name }} at this time.',
        'status_label' => 'Not approved',
        'status_type' => 'warning',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — Agent application rejected',
        'heading' => 'Application rejected',
        'intro' => 'An agent application was rejected. Internal records have been updated.',
        'status_label' => 'Rejected',
        'status_type' => 'warning',
      ],
    ],
    'agent_application_needs_more_info' => [
      'applicant' => [
        'subject' => '{{ brand_name }} — More information needed for your application',
        'heading' => 'Additional information required',
        'intro' => 'We need more information to continue reviewing your agent application for {{ company_name }}.',
        'status_label' => 'Action required',
        'status_type' => 'warning',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — Agent application needs more information',
        'heading' => 'Application on hold',
        'intro' => 'An agent application was marked as needing more information.',
        'status_label' => 'On hold',
        'status_type' => 'info',
      ],
    ],
    'staff_created' => [
      'staff' => [
        'subject' => '{{ brand_name }} — Your {{ recipient_designation }} account',
        'heading' => 'Account created',
        'intro' => 'Your {{ recipient_designation }} account for {{ agency_name }} has been created.',
        'status_label' => 'Active',
        'status_type' => 'success',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — New staff account created',
        'heading' => 'Staff account created',
        'intro' => 'A new staff account was created for {{ agency_name }}.',
        'status_label' => 'Created',
        'status_type' => 'info',
      ],
    ],
    'agent_created' => [
      'agent' => [
        'subject' => '{{ brand_name }} — Your agent account is ready',
        'heading' => 'Agent account created',
        'intro' => 'Your agent account for {{ agency_name }} has been created.',
        'status_label' => 'Active',
        'status_type' => 'success',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — New agent account created',
        'heading' => 'Agent account created',
        'intro' => 'A new agent account was created for {{ agency_name }}.',
        'status_label' => 'Created',
        'status_type' => 'info',
      ],
    ],
    'user_suspended' => [
      'user' => [
        'subject' => '{{ brand_name }} — Your account has been suspended',
        'heading' => 'Account suspended',
        'intro' => 'Your {{ recipient_designation }} account has been suspended. Contact support if you believe this is an error.',
        'status_label' => 'Suspended',
        'status_type' => 'warning',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — User account suspended',
        'heading' => 'Account suspended',
        'intro' => 'A user account was suspended and may require follow-up.',
        'status_label' => 'Suspended',
        'status_type' => 'warning',
      ],
    ],
    'user_activated' => [
      'user' => [
        'subject' => '{{ brand_name }} — Your account is active again',
        'heading' => 'Account reactivated',
        'intro' => 'Your {{ recipient_designation }} account has been reactivated. You may sign in again.',
        'status_label' => 'Active',
        'status_type' => 'success',
      ],
      'admin' => [
        'subject' => '{{ brand_name }} — User account reactivated',
        'heading' => 'Account reactivated',
        'intro' => 'A previously suspended user account was reactivated.',
        'status_label' => 'Active',
        'status_type' => 'success',
      ],
    ],
  ],

  /*
  | Bucket aliases for lifecycle events not in NotificationRecipientResolver POLICY_BUCKETS.
  */
  'recipient_policies' => [
    'staff_created' => ['staff', 'admin'],
    'agent_created' => ['agent', 'admin'],
    'user_suspended' => ['user', 'admin'],
    'user_activated' => ['user', 'admin'],
  ],

  /*
  | Deduplication: prevent duplicate sends within the cooldown window (minutes).
  */
  'dedup_minutes' => 5,

  /*
  | Rejection markers for operational email brand scanning — not fallback values.
  */
  'prohibited_brand_markers' => [
    'Parwaaz',
    'parwaaz',
    'Parwaaz Travels',
    'YD Travel',
    'YoursDomain',
    'yoursdomain',
    'haseeb-master',
    'placeholder 123',
  ],

  // @deprecated alias — use prohibited_brand_markers
  'forbidden_brand_fragments' => [
    'Parwaaz',
    'parwaaz',
    'Parwaaz Travels',
    'YD Travel',
    'YoursDomain',
    'yoursdomain',
    'haseeb-master',
    'placeholder 123',
  ],

];
