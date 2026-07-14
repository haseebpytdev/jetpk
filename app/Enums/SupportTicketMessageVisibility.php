<?php

namespace App\Enums;

enum SupportTicketMessageVisibility: string
{
    case CustomerVisible = 'customer_visible';
    case Internal = 'internal';
}
