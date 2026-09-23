<?php

return [
    'enabled' => (bool) env('AI_EMBED_ENABLED', false),

    'public_base_url' => rtrim((string) env('AI_EMBED_PUBLIC_BASE_URL', env('APP_URL', '')), '/'),

    'session_ttl_seconds' => (int) env('AI_EMBED_SESSION_TTL', 14400),

    'session_header' => 'X-JP-AI-Embed-Session',

    'parent_origin_header' => 'X-JP-AI-Embed-Parent-Origin',

    /*
     * Legacy env bridge for JetPakistan tenant #1 during migration to DB registry.
     * Prefer ai-embed:tenant-sync / ai-embed:tenant-upsert for operational changes.
     */
    'legacy_env_bridge' => [
        'jetpakistan_path' => (string) env('AI_EMBED_JETPAKISTAN_PATH', ''),
        'jetpakistan_allowed_origins' => array_values(array_filter(array_map(
            static fn (string $origin): string => trim($origin),
            explode(',', (string) env('AI_EMBED_JETPAKISTAN_ALLOWED_ORIGINS', ''))
        ))),
    ],
];
