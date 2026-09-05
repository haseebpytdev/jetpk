<?php

namespace App\Support\Emails;

use Illuminate\Support\Str;

final class JetpkEmailQaCorrelation
{
    public static function make(string $scenarioId): string
    {
        $scenario = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '-', $scenarioId) ?: 'SCENARIO');
        $scenario = trim($scenario, '-');

        return 'JP-EMAIL-PROD-QA-02-'.$scenario.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
