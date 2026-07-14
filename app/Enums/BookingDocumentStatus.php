<?php

namespace App\Enums;

enum BookingDocumentStatus: string
{
    case Generated = 'generated';
    case Failed = 'failed';
}
