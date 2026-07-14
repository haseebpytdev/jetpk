<?php

namespace App\View\Components\Support;

use App\Models\SupportTicket;
use App\Support\Support\SupportTicketTimelineBuilder;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class TicketTimeline extends Component
{
    /** @var list<array{key: string, label: string, state: string, detail: string|null, at: string|null}> */
    public array $steps;

    public function __construct(
        public SupportTicket $ticket,
        public string $audience = SupportTicketTimelineBuilder::AUDIENCE_INTERNAL,
        public string $variant = 'dashboard',
        ?SupportTicketTimelineBuilder $builder = null,
    ) {
        $ticket->loadMissing(['assignedTo', 'forwardedToAgent.user', 'forwardedBy']);

        $this->steps = ($builder ?? app(SupportTicketTimelineBuilder::class))->build($ticket, $audience);
    }

    public function render(): View|Closure|string
    {
        return view('components.support.ticket-timeline');
    }
}
