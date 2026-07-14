<?php

namespace App\Enums;

enum JetpkHomepageFareRefreshStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case NoResults = 'no_results';
    case Manual = 'manual';
    case Stale = 'stale';
}
