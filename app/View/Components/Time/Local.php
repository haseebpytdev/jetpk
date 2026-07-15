<?php

namespace App\View\Components\Time;

use App\Models\Agency;
use App\Support\Time\DisplayTimezoneResolver;
use App\Support\Time\LocalTimeDisplay;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\View\Component;

class Local extends Component
{
    public string $label = '';

    public string $utcTitle = '';

    public bool $hasValue = false;

    public function __construct(
        public Carbon|string|null $value = null,
        public string $context = 'public',
        public bool $showUtc = true,
        public string $empty = '—',
        ?DisplayTimezoneResolver $resolver = null,
        ?LocalTimeDisplay $display = null,
    ) {
        $resolver ??= app(DisplayTimezoneResolver::class);
        $display ??= app(LocalTimeDisplay::class);

        $timezone = $this->resolveTimezone($resolver);
        $includeUtc = $this->context === 'operator' && $this->showUtc;
        $formatted = $display->format($this->value, $timezone, $includeUtc);

        if ($formatted === null) {
            $this->label = $this->empty;
            $this->hasValue = false;

            return;
        }

        $this->label = $formatted['label'];
        $this->utcTitle = $formatted['utc_title'];
        $this->hasValue = true;
    }

    private function resolveTimezone(DisplayTimezoneResolver $resolver): string
    {
        if ($this->context === 'operator') {
            $user = auth()->user();
            $agency = $user?->currentAgency;

            return $resolver->userTimezone($user, $agency instanceof Agency ? $agency : null);
        }

        return $resolver->visitorTimezone(request());
    }

    public function render(): View|Closure|string
    {
        return view('components.time.local');
    }
}
