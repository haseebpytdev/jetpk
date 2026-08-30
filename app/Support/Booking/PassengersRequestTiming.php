<?php

namespace App\Support\Booking;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Non-PII server timeline for GET/POST /booking/passengers (JP-FINAL-CLOSURE R5).
 * Marks S0–S8; correlatable via X-JP-Book-Now-Id when the client sends it.
 */
final class PassengersRequestTiming
{
    public const HEADER = 'X-JP-Book-Now-Id';

    /** @var array<string, float> */
    private array $marks = [];

    private readonly float $t0;

    private readonly string $correlationId;

    private function __construct(string $correlationId)
    {
        $this->correlationId = $correlationId;
        $this->t0 = microtime(true);
        $this->mark('S0_request_received');
    }

    public static function start(Request $request): self
    {
        $fromHeader = trim((string) $request->headers->get(self::HEADER, ''));
        $id = $fromHeader !== '' ? substr($fromHeader, 0, 64) : ('srv-'.bin2hex(random_bytes(6)));

        $timing = new self($id);
        $request->attributes->set('jp_passengers_timing', $timing);

        return $timing;
    }

    public static function fromRequest(Request $request): ?self
    {
        $timing = $request->attributes->get('jp_passengers_timing');

        return $timing instanceof self ? $timing : null;
    }

    public function mark(string $name): void
    {
        $this->marks[$name] = microtime(true);
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toLogContext(): array
    {
        $deltas = [];
        foreach ($this->marks as $name => $at) {
            $deltas[$name.'_ms'] = (int) round(($at - $this->t0) * 1000);
        }

        $totalMs = (int) round((microtime(true) - $this->t0) * 1000);

        return array_merge([
            'correlation_id' => $this->correlationId,
            'total_ms' => $totalMs,
        ], $deltas);
    }

    public function finish(string $outcome = 'ok'): void
    {
        $this->mark('S8_response_dispatched');
        Log::info('jp.booking.passengers_timing', array_merge($this->toLogContext(), [
            'outcome' => $outcome,
        ]));
    }
}
