<?php

namespace App\Enums;

enum AgentWalletStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';

    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}
