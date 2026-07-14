<?php

namespace App\Enums;

enum MarkupRuleStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Draft = 'draft';
}
