<?php

namespace App\Support\OneApi;

/**
 * Live-send gate for One API Artisan probes (search, book, payment modification).
 */
final class OneApiMutationCommandGate
{
    /**
     * @param  list<string>  $requiredConfigFlags
     * @return array{live_allowed: bool, blockers: list<string>}
     */
    public static function evaluateLive(
        bool $confirmFlag,
        array $requiredConfigFlags,
    ): array {
        $blockers = [];
        if (! $confirmFlag) {
            $blockers[] = 'confirm_flag_missing';
        }

        foreach ($requiredConfigFlags as $flag) {
            if (! (bool) config($flag, false)) {
                $blockers[] = 'config_gate_'.str_replace('.', '_', $flag);
            }
        }

        $liveAllowed = $confirmFlag && $blockers === [];

        return [
            'live_allowed' => $liveAllowed,
            'blockers' => array_values(array_unique($blockers)),
        ];
    }
}
