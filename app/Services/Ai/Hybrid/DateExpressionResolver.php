<?php

namespace App\Services\Ai\Hybrid;

use Carbon\Carbon;

/**
 * Application-owned date expression resolution.
 */
final class DateExpressionResolver
{
    /**
     * @return array{date: ?string, clarify: bool, clarify_message: ?string, provenance: ?string, delta_days: ?int}
     */
    public function resolveDepart(string $normalized, string $original, ?Carbon $now = null, ?string $priorDate = null): array
    {
        $now ??= Carbon::now();

        if (str_contains($normalized, 'one day later')
            || str_contains($normalized, 'aik din baad')
            || str_contains($normalized, 'agle din')
            || str_contains($original, 'ایک دن بعد')) {
            if (is_string($priorDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $priorDate) === 1) {
                return [
                    'date' => Carbon::parse($priorDate)->addDay()->toDateString(),
                    'clarify' => false,
                    'clarify_message' => null,
                    'provenance' => 'INHERITED_CONVERSATION_STATE',
                    'delta_days' => 1,
                ];
            }

            return [
                'date' => null,
                'clarify' => true,
                'clarify_message' => 'Which departure date should I move one day later?',
                'provenance' => null,
                'delta_days' => 1,
            ];
        }

        if (preg_match('/\b(\d+)\s*din baad\b/u', $normalized, $m) === 1 && is_string($priorDate)) {
            return [
                'date' => Carbon::parse($priorDate)->addDays((int) $m[1])->toDateString(),
                'clarify' => false,
                'clarify_message' => null,
                'provenance' => 'INHERITED_CONVERSATION_STATE',
                'delta_days' => (int) $m[1],
            ];
        }

        if (preg_match('/\btoday\b|\baaj\b|آج/u', $normalized.' '.$original) === 1) {
            return $this->ok($now->toDateString(), 'DETERMINISTIC_NORMALIZATION');
        }
        if (preg_match('/\btomorrow\b|\bkal\b|کل/u', $normalized.' '.$original) === 1) {
            return $this->ok($now->copy()->addDay()->toDateString(), 'DETERMINISTIC_NORMALIZATION');
        }
        if (preg_match('/\bparso\b|پرسوں|day after tomorrow/u', $normalized.' '.$original) === 1) {
            return $this->ok($now->copy()->addDays(2)->toDateString(), 'DETERMINISTIC_NORMALIZATION');
        }
        if (preg_match('/next\s+friday|aglay\s+jumay|aglay\s+jumma|اگلے\s+جمعہ/u', $normalized.' '.$original) === 1) {
            return $this->ok($now->copy()->next(Carbon::FRIDAY)->toDateString(), 'DETERMINISTIC_NORMALIZATION');
        }
        if (preg_match('/next\s+(monday|tuesday|wednesday|thursday|friday|saturday|sunday)/u', $normalized, $m) === 1) {
            return $this->ok($now->copy()->next($m[1])->toDateString(), 'DETERMINISTIC_NORMALIZATION');
        }
        if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $normalized, $m) === 1) {
            return $this->ok($m[1], 'EXPLICIT_USER');
        }
        if (preg_match('/\b(\d{1,2})\s+(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t|tember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\b/i', $normalized, $m) === 1) {
            try {
                $dt = Carbon::parse(sprintf('%d %s %d', (int) $m[1], $m[2], $now->year));
                if ($dt->lt($now->copy()->startOfDay())) {
                    $dt->addYear();
                }

                return $this->ok($dt->toDateString(), 'DETERMINISTIC_NORMALIZATION');
            } catch (\Throwable) {
                // fall through
            }
        }

        // "18 ko" without month — clarify
        if (preg_match('/\b(\d{1,2})\s*ko\b/u', $normalized, $m) === 1
            && preg_match('/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/i', $normalized) !== 1) {
            return [
                'date' => null,
                'clarify' => true,
                'clarify_message' => 'What month do you mean by the '.$m[1].'th?',
                'provenance' => null,
                'delta_days' => null,
            ];
        }

        return ['date' => null, 'clarify' => false, 'clarify_message' => null, 'provenance' => null, 'delta_days' => null];
    }

    /**
     * Shared return-date cue (optional "on") + date token capture group 1.
     * Consumed by resolveReturn and ServerTravelSignals strip — do not duplicate.
     */
    public function returnDateCueRegex(): string
    {
        return '/(?:,?\s*)?(?:'
            .'(?:i\s+also\s+need\s+to\s+)?(?:come|coming)\s+back'
            .'|return(?:ing)?(?:\s+date)?'
            .'|wapis|wapas|make it return'
            .')(?:\s+on)?\s+'
            .'('
            .'(?:next\s+)?(?:sunday|monday|tuesday|wednesday|thursday|friday|saturday)'
            .'|\d{1,2}\s+[A-Za-z]+'
            .'|\d{4}-\d{2}-\d{2}'
            .'|\d{1,2}(?:st|nd|rd|th)?'
            .')/iu';
    }

    /**
     * @return array{date: ?string, clarify: bool, clarify_message: ?string, provenance: ?string}
     */
    public function resolveReturn(string $normalized, string $original, ?Carbon $now = null, ?string $priorDepart = null): array
    {
        $now ??= Carbon::now();
        $anchor = $now->copy()->startOfDay();
        if (is_string($priorDepart) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $priorDepart) === 1) {
            $anchor = Carbon::parse($priorDepart)->startOfDay();
        }

        if (preg_match($this->returnDateCueRegex(), $normalized, $m) === 1
            || preg_match('/\b(\d{1,2})\s+ko\s+wapas\b/u', $normalized, $m) === 1
            || preg_match('/\b(?:wapis|wapas)\s+(sunday|monday|tuesday|wednesday|thursday|friday|saturday)\b/iu', $normalized, $m) === 1) {
            try {
                $token = $m[1];
                if (preg_match('/^\d{1,2}(?:st|nd|rd|th)?$/i', $token) === 1) {
                    $day = (int) $token;

                    return [
                        'date' => null,
                        'clarify' => true,
                        'clarify_message' => 'What month is the return on the '.$day.'th?',
                        'provenance' => null,
                    ];
                }
                if (preg_match('/^\d{1,2}$/', $token) === 1) {
                    return [
                        'date' => null,
                        'clarify' => true,
                        'clarify_message' => 'What month is the return on the '.$token.'th?',
                        'provenance' => null,
                    ];
                }
                if (preg_match('/^(?:next\s+)?(sunday|monday|tuesday|wednesday|thursday|friday|saturday)$/i', $token, $wm) === 1) {
                    // First matching weekday strictly AFTER outbound departure (or now).
                    $dt = $anchor->copy()->next($wm[1]);
                    if (! $dt->gt($anchor)) {
                        $dt->addWeek();
                    }

                    return [
                        'date' => $dt->toDateString(),
                        'clarify' => false,
                        'clarify_message' => null,
                        'provenance' => 'DETERMINISTIC_NORMALIZATION',
                    ];
                }
                $dt = Carbon::parse($token.' '.$now->year);
                if (is_string($priorDepart) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $priorDepart) === 1) {
                    while ($dt->lte(Carbon::parse($priorDepart)->startOfDay())) {
                        $dt->addYear();
                    }
                } elseif ($dt->isPast()) {
                    $dt->addYear();
                }

                return [
                    'date' => $dt->toDateString(),
                    'clarify' => false,
                    'clarify_message' => null,
                    'provenance' => 'DETERMINISTIC_NORMALIZATION',
                ];
            } catch (\Throwable) {
                return ['date' => null, 'clarify' => false, 'clarify_message' => null, 'provenance' => null];
            }
        }
        if (preg_match('/واپسی\s*([\p{Arabic}\d\s]+)/u', $original, $m) === 1) {
            // Leave complex Urdu month phrases for clarify if not numeric ISO
            return [
                'date' => null,
                'clarify' => true,
                'clarify_message' => 'Please share the return date as day and month (for example 23 Sep).',
                'provenance' => null,
            ];
        }

        return ['date' => null, 'clarify' => false, 'clarify_message' => null, 'provenance' => null];
    }

    /**
     * @return array{date: ?string, clarify: bool, clarify_message: ?string, provenance: ?string, delta_days: ?int}
     */
    private function ok(string $date, string $prov): array
    {
        return [
            'date' => $date,
            'clarify' => false,
            'clarify_message' => null,
            'provenance' => $prov,
            'delta_days' => null,
        ];
    }
}
