<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $assistantName }}</title>
    <link rel="stylesheet" href="{{ asset('css/ai-embed.css') }}?v=1">
    @if(!empty($themePrimary))
        <style>:root { --jp-ai-embed-primary: {{ $themePrimary }}; }</style>
    @endif
</head>
<body>
<div id="jp-ai-embed" class="jp-ai-embed" data-tenant-public-id="{{ $tenantPublicId }}">
    <header class="jp-ai-embed__header">
        <div class="jp-ai-embed__brand">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="" class="jp-ai-embed__logo" width="32" height="32">
            @endif
            <div>
                <p class="jp-ai-embed__eyebrow">{{ $displayName }}</p>
                <h1 class="jp-ai-embed__title">{{ $assistantName }}</h1>
            </div>
        </div>
        <div class="jp-ai-embed__header-actions">
            <button type="button" class="jp-ai-embed__btn jp-ai-embed__btn--ghost" id="jp-ai-handoff" hidden>Talk to support</button>
            <button type="button" class="jp-ai-embed__btn jp-ai-embed__btn--ghost" id="jp-ai-clear" hidden>New chat</button>
        </div>
    </header>

    <main class="jp-ai-embed__main">
        <div id="jp-ai-status" class="jp-ai-embed__status" role="status"></div>
        @if(!empty($welcomeText))
            <p class="jp-ai-embed__welcome">{{ $welcomeText }}</p>
        @endif
        <div id="jp-ai-messages" class="jp-ai-embed__messages" aria-live="polite"></div>
    </main>

    <footer class="jp-ai-embed__footer">
        <form id="jp-ai-form" class="jp-ai-embed__form">
            <label class="sr-only" for="jp-ai-input">Message</label>
            <textarea
                id="jp-ai-input"
                class="jp-ai-embed__input"
                rows="1"
                maxlength="2000"
                placeholder="Ask about flights, bookings, or support..."
                autocomplete="off"
            ></textarea>
            <button type="submit" class="jp-ai-embed__btn jp-ai-embed__btn--primary" id="jp-ai-send">Send</button>
        </form>
        <p class="jp-ai-embed__footnote">Read-only travel help. No booking writes in chat.</p>
    </footer>
</div>

<script>
window.JP_AI_EMBED = {
    tenantPublicId: @json($tenantPublicId),
    sessionEndpoint: @json($sessionEndpoint),
    chatEndpoint: @json($chatEndpoint),
    messagesEndpoint: @json($messagesEndpoint),
    clearEndpoint: @json($clearEndpoint),
    handoffEndpoint: @json($handoffEndpoint),
    sessionHeader: @json($sessionHeader),
    parentOriginHeader: @json($parentOriginHeader),
    storageKey: 'jp_ai_embed_session',
    conversationKey: 'jp_ai_embed_conversation_id',
};
</script>
<script src="{{ asset('js/ai-embed.js') }}?v=1" defer></script>
</body>
</html>
