{{-- JP-PORTAL-3 TASK 4 · Role-neutral JetPK portal support thread
     Replaces, on JetPK-resolved pages only:
       dashboard.support._thread   (the loop + empty state)
       dashboard.support._message  (a single message card)

     Both legacy partials REMAIN on disk untouched and serve legacy dashboard clients when standalone mode is off
     and the admin/staff dashboard. This component does not modify the support-ticket backend.

     ROLE-NEUTRAL BY DESIGN. Used by:
       themes/customer/jetpakistan/support/tickets/show.blade.php  (audience="customer")
       themes/agent/jetpakistan/support/tickets/show.blade.php     (audience="agent")  — Task 8
     `audience` carries the caller's context only. It MUST NOT be used to decide whether an
     internal message is rendered: that decision belongs to the backend, which controls what
     lands in $messages. Filtering here would silently diverge the two roles' views of a ticket.

     Preserved verbatim from the legacy partials:
       • @forelse over $messages with an empty state ("No messages yet" / "Replies will appear here.")
       • internal detection: $message->visibility->value === 'internal'  (identical expression)
       • internal messages receive a distinct visual treatment + an "Internal" badge
       • author fallback: e($message->author?->name ?? 'Support')
       • message body ESCAPED via e() — never {!! !!}
       • timestamp via <x-time.local :value="$message->created_at" context="operator" />
         (canonical component — reused, so timezone rendering is unchanged)
       • data-testids: support-ticket-thread, support-message

     ATTACHMENTS: the legacy partials render NO attachment UI. There is no attachment contract to
     preserve, so none is invented here. If the backend later exposes message attachments, this is
     the single place to add them for every role at once.

     @param \Illuminate\Support\Collection|iterable $messages
     @param string $audience  'customer'|'agent'  — context label only; not an authorization gate
--}}
@php
    $audience = $audience ?? 'customer';
@endphp

<div class="jp-portal__stack jp-support-thread" data-testid="support-ticket-thread" data-audience="{{ $audience }}">
    @forelse ($messages as $message)
        @php
            $isInternal = $message->visibility->value === 'internal';
        @endphp
        <article class="jp-support-message {{ $isInternal ? 'jp-support-message--internal' : '' }}" data-testid="support-message">
            <header class="jp-support-message__head">
                <span class="jp-support-message__author">
                    {{ e($message->author?->name ?? 'Support') }}
                    @if ($isInternal)
                        <span class="jp-badge jp-badge--warning">Internal</span>
                    @endif
                </span>
                <span class="jp-support-message__time">
                    <x-time.local :value="$message->created_at" context="operator" />
                </span>
            </header>
            <p class="jp-support-message__body">{{ e($message->body) }}</p>
        </article>
    @empty
        <x-jp.card class="jp-portal__panel">
            <div class="jp-empty">
                <span class="jp-empty__icon" aria-hidden="true"><x-jp.icon name="message-circle" /></span>
                <p class="jp-empty__title">No messages yet</p>
                <p class="jp-empty__help">Replies will appear here.</p>
            </div>
        </x-jp.card>
    @endforelse
</div>
