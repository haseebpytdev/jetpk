<?php

/**
 * Controlled AI lab adapter (JP-AI-CONTROLLED-INTEGRATION-01).
 * Separate from ota.ai_assistant.gateway_url (LocalLlama :3921).
 */
return [
    /**
     * Infrastructure hard ceilings for lab adapter capabilities.
     * Admin DB settings cannot exceed these values.
     */
    'hard_allow' => [
        'lab_adapter' => filter_var(
            env('OTA_AI_LAB_ADAPTER_HARD_ALLOW', filter_var(env('OTA_AI_LAB_ADAPTER_ENABLED', false), FILTER_VALIDATE_BOOL)
                || filter_var(env('OTA_AI_LAB_CANARY_ONLY', false), FILTER_VALIDATE_BOOL)),
            FILTER_VALIDATE_BOOL
        ),
        'rag' => filter_var(env('OTA_AI_LAB_RAG_HARD_ALLOW', filter_var(env('OTA_AI_LAB_RAG_ENABLED', true), FILTER_VALIDATE_BOOL)), FILTER_VALIDATE_BOOL),
        'learning_queue' => filter_var(env('OTA_AI_LAB_LEARNING_HARD_ALLOW', filter_var(env('OTA_AI_LAB_LEARNING_ENABLED', true), FILTER_VALIDATE_BOOL)), FILTER_VALIDATE_BOOL),
    ],

    /** Master switch — default off; shadow integration only when enabled. */
    'enabled' => filter_var(env('OTA_AI_LAB_ADAPTER_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Internal canary only — lab path for platform admins / support staff when global enable is off. */
    'canary_only' => filter_var(env('OTA_AI_LAB_CANARY_ONLY', false), FILTER_VALIDATE_BOOL),

    'contract_version' => 'v1',

    /** Localhost-only consultant gateway (Python lab wrapper). */
    'gateway_url' => env('OTA_AI_LAB_GATEWAY_URL', 'http://127.0.0.1:8765'),
    'gateway_timeout_ms' => max(1000, (int) env('OTA_AI_LAB_GATEWAY_TIMEOUT_MS', 8000)),
    'require_localhost' => true,

    /** Pinned lab source for certification traceability. */
    'lab_repo_path' => env('OTA_AI_LAB_REPO_PATH', base_path('tmp/ai-lab')),
    'lab_git_sha' => '7977f19f5e35eedfca27504a53cfdff78e35f0ba',

    /** Shadow mode — never call live suppliers through lab path. */
    'shadow_flight_search' => filter_var(env('OTA_AI_LAB_SHADOW_FLIGHT', true), FILTER_VALIDATE_BOOL),
    'allow_live_supplier' => false,

    'rag_enabled' => filter_var(env('OTA_AI_LAB_RAG_ENABLED', true), FILTER_VALIDATE_BOOL),
    'rag_block_live_data' => true,

    'mock_handoff_requires_consent' => true,

    'learning_queue_enabled' => filter_var(env('OTA_AI_LAB_LEARNING_ENABLED', true), FILTER_VALIDATE_BOOL),
    'learning_driver' => env('OTA_AI_LAB_LEARNING_DRIVER', 'jsonl'),

    /** When gateway fails, return safe unavailable — do not fall back to live supplier tools. */
    'fallback_to_legacy' => filter_var(env('OTA_AI_LAB_FALLBACK_LEGACY', false), FILTER_VALIDATE_BOOL),

    /** Shared secret for authorized internal-canary HTTP fault injection (harness only). */
    'canary_fault_token' => env('OTA_AI_LAB_CANARY_FAULT_TOKEN', ''),
];
