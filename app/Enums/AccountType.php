<?php

namespace App\Enums;

enum AccountType: string
{
    case PlatformAdmin = 'platform_admin';
    case AgencyAdmin = 'agency_admin';
    case Staff = 'staff';
    case Agent = 'agent';
    case AgentStaff = 'agent_staff';
    case Customer = 'customer';
}
