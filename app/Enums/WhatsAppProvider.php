<?php

namespace App\Enums;

enum WhatsAppProvider: string
{
    case MetaCloudApi = 'meta_cloud_api';
    case Twilio = 'twilio';
    case Custom = 'custom';
}
