<?php

namespace App\Support\Sabre\Scenario;

/**
 * Hard in-memory limiter: at most one supplier revalidation HTTP call per probe execution.
 */
final class SabreGdsRevalidationProbeCallCounter
{
    private int $count = 0;

    public function recordCall(): void
    {
        $this->count++;
        if ($this->count > 1) {
            throw new \RuntimeException('supplier_revalidation_call_limit_exceeded');
        }
    }

    public function count(): int
    {
        return $this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }
}
