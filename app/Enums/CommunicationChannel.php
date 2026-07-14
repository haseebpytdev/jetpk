<?php

namespace App\Enums;

enum CommunicationChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case System = 'system';
}
