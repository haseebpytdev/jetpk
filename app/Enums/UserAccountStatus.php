<?php

namespace App\Enums;

enum UserAccountStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';
    case Inactive = 'inactive';
}
