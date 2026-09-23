<?php

return [
    'enabled' => (bool) env('AI_EMBED_ENABLED', false),

    'session_ttl_seconds' => (int) env('AI_EMBED_SESSION_TTL', 14400),

    'session_header' => 'X-JP-AI-Embed-Session',

    'parent_origin_header' => 'X-JP-AI-Embed-Parent-Origin',

    /*
     * Unlisted iframe entry slug for Embed-01 (JetPakistan only).
     * High-entropy, URL-safe value from AI_EMBED_JETPAKISTAN_PATH — not a security boundary.
     */
    'entry_paths' => [
        'jetpakistan' => (string) env('AI_EMBED_JETPAKISTAN_PATH', ''),
    ],

    'tenants' => [
        'jetpakistan' => [
            'display_name' => 'JetPakistan',
            'assistant_name' => 'Ask JetPakistan',
            'allowed_origins' => array_values(array_filter(array_map(
                static fn (string $origin): string => trim($origin),
                explode(',', (string) env('AI_EMBED_JETPAKISTAN_ALLOWED_ORIGINS', ''))
            ))),
        ],
    ],
];
