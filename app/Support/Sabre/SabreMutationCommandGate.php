<?php

namespace App\Support\Sabre;

/**
 * Shared dry-run / live-send gate for Sabre mutation Artisan commands.
 */
final class SabreMutationCommandGate
{
    /**
     * @param  list<string>  $requiredEnvFlags  Config paths that must be true for live send (e.g. suppliers.sabre.ticketing_live_call_enabled)
     * @return array{
     *     dry_run: bool,
     *     send_requested: bool,
     *     confirm_provided: bool,
     *     confirm_matches: bool,
     *     live_allowed: bool,
     *     blockers: list<string>
     * }
     */
    public static function evaluate(
        bool $dryRunOption,
        ?string $sendOption,
        ?string $confirmProvided,
        string $expectedConfirmPhrase,
        array $requiredEnvFlags = [],
    ): array {
        $sendRequested = $sendOption !== null && $sendOption !== '0' && strtolower((string) $sendOption) !== 'false';
        $dryRun = $dryRunOption || ! $sendRequested;
        $confirmProvided = is_string($confirmProvided) && trim($confirmProvided) !== '';
        $confirmMatches = $confirmProvided && trim($confirmProvided) === trim($expectedConfirmPhrase);

        $blockers = [];
        if ($dryRun) {
            $blockers[] = 'dry_run_default';
        }
        if ($sendRequested && ! $confirmMatches) {
            $blockers[] = 'confirm_phrase_mismatch';
        }

        foreach ($requiredEnvFlags as $flag) {
            if (! (bool) config($flag, false)) {
                $blockers[] = 'env_gate_'.str_replace('.', '_', $flag);
            }
        }

        $liveAllowed = $sendRequested && $confirmMatches && ! $dryRun;
        foreach ($requiredEnvFlags as $flag) {
            if (! (bool) config($flag, false)) {
                $liveAllowed = false;
            }
        }

        return [
            'dry_run' => $dryRun,
            'send_requested' => $sendRequested,
            'confirm_provided' => $confirmProvided,
            'confirm_matches' => $confirmMatches,
            'live_allowed' => $liveAllowed,
            'blockers' => array_values(array_unique($blockers)),
        ];
    }
}
