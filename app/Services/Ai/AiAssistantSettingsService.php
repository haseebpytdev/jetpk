<?php

namespace App\Services\Ai;

use App\Models\AiAssistantSetting;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/**
 * Platform singleton AI assistant controls with env hard-ceiling AND-gates.
 *
 * EFFECTIVE = ENV_HARD_ALLOW ∧ ADMIN_PERSISTED_SETTING ∧ (master when applicable)
 */
final class AiAssistantSettingsService
{
    /**
     * @return list<array{key: string, label: string, locked: bool, activatable: bool}>
     */
    public function hardLockedWriteCapabilities(): array
    {
        return [
            ['key' => 'booking', 'label' => 'Booking', 'locked' => true, 'activatable' => false],
            ['key' => 'hold', 'label' => 'Hold', 'locked' => true, 'activatable' => false],
            ['key' => 'create_pnr', 'label' => 'Create PNR', 'locked' => true, 'activatable' => false],
            ['key' => 'issue_ticket', 'label' => 'Issue ticket', 'locked' => true, 'activatable' => false],
            ['key' => 'payment', 'label' => 'Payment', 'locked' => true, 'activatable' => false],
            ['key' => 'cancel', 'label' => 'Cancel', 'locked' => true, 'activatable' => false],
            ['key' => 'refund', 'label' => 'Refund', 'locked' => true, 'activatable' => false],
            ['key' => 'void', 'label' => 'Void', 'locked' => true, 'activatable' => false],
            ['key' => 'exchange', 'label' => 'Exchange', 'locked' => true, 'activatable' => false],
            ['key' => 'live_supplier_write', 'label' => 'Live supplier write', 'locked' => true, 'activatable' => false],
        ];
    }

    public function get(): AiAssistantSetting
    {
        if (! Schema::hasTable('ai_assistant_settings')) {
            return $this->fallbackModel();
        }

        $existing = AiAssistantSetting::query()->first();
        if ($existing !== null) {
            return $existing;
        }

        return AiAssistantSetting::query()->create($this->bootstrapDefaults());
    }

    /**
     * @return array<string, bool>
     */
    public function envHardAllows(): array
    {
        return [
            'master' => (bool) config('ota.ai_assistant.hard_allow.master', false),
            'lab_adapter' => (bool) config('ai_lab.hard_allow.lab_adapter', false),
            'rag' => (bool) config('ai_lab.hard_allow.rag', false),
            'human_handoff' => (bool) config('ota.ai_assistant.hard_allow.human_handoff', false),
            'learning_queue' => (bool) config('ai_lab.hard_allow.learning_queue', false),
            'internal_canary' => (bool) config('ota.ai_assistant.hard_allow.internal_canary', false),
            'flight_search_read_only' => (bool) config('ota.ai_assistant.hard_allow.flight_search_read_only', false),
            'public' => (bool) config('ota.ai_assistant.hard_allow.public', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function adminValues(): array
    {
        $settings = $this->get();

        return [
            'master_enabled' => (bool) $settings->master_enabled,
            'lab_adapter_enabled' => (bool) $settings->lab_adapter_enabled,
            'rag_enabled' => (bool) $settings->rag_enabled,
            'human_handoff_enabled' => (bool) $settings->human_handoff_enabled,
            'learning_queue_enabled' => (bool) $settings->learning_queue_enabled,
            'internal_canary_enabled' => (bool) $settings->internal_canary_enabled,
            'flight_search_read_only_enabled' => (bool) $settings->flight_search_read_only_enabled,
            'audience_mode' => (string) ($settings->audience_mode ?: AiAssistantSetting::AUDIENCE_OFF),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function effective(): array
    {
        $hard = $this->envHardAllows();
        $admin = $this->adminValues();

        $master = $hard['master'] && $admin['master_enabled'];

        $audienceMode = $this->resolveEffectiveAudienceMode($master, $admin, $hard);

        return [
            'master_enabled' => $master,
            'lab_adapter_enabled' => $master && $hard['lab_adapter'] && $admin['lab_adapter_enabled'],
            'rag_enabled' => $master && $hard['rag'] && $admin['rag_enabled'],
            'human_handoff_enabled' => $master && $hard['human_handoff'] && $admin['human_handoff_enabled'],
            'learning_queue_enabled' => $master && $hard['learning_queue'] && $admin['learning_queue_enabled'],
            'internal_canary_enabled' => $master && $hard['internal_canary'] && $admin['internal_canary_enabled'],
            'flight_search_read_only_enabled' => $master && $hard['flight_search_read_only'] && $admin['flight_search_read_only_enabled'],
            'audience_mode' => $audienceMode,
            'public_enabled' => $audienceMode === AiAssistantSetting::AUDIENCE_PUBLIC,
            'runtime_on' => $master && $audienceMode !== AiAssistantSetting::AUDIENCE_OFF,
        ];
    }

    /**
     * @return array<string, array{configured: bool, effective: bool, blocked_by: ?string}>
     */
    public function controlMatrix(): array
    {
        $hard = $this->envHardAllows();
        $admin = $this->adminValues();
        $effective = $this->effective();

        $controls = [
            'master_enabled' => ['label' => 'Master AI assistant', 'hard_key' => 'master'],
            'lab_adapter_enabled' => ['label' => 'AI lab adapter', 'hard_key' => 'lab_adapter'],
            'rag_enabled' => ['label' => 'RAG', 'hard_key' => 'rag'],
            'human_handoff_enabled' => ['label' => 'Support handoff', 'hard_key' => 'human_handoff'],
            'learning_queue_enabled' => ['label' => 'Learning / failure logging', 'hard_key' => 'learning_queue'],
            'internal_canary_enabled' => ['label' => 'Internal canary', 'hard_key' => 'internal_canary'],
            'flight_search_read_only_enabled' => ['label' => 'QA read-only flight search', 'hard_key' => 'flight_search_read_only'],
        ];

        $matrix = [];
        foreach ($controls as $field => $meta) {
            $configured = (bool) ($admin[$field] ?? false);
            $effectiveValue = (bool) ($effective[$field] ?? false);
            $blockedBy = null;
            if ($configured && ! $effectiveValue) {
                if (! ($hard[$meta['hard_key']] ?? false)) {
                    $blockedBy = 'environment_hard_ceiling';
                } elseif ($field !== 'master_enabled' && ! ($effective['master_enabled'] ?? false)) {
                    $blockedBy = 'master_disabled';
                } else {
                    $blockedBy = 'policy';
                }
            }
            $matrix[$field] = [
                'label' => $meta['label'],
                'configured' => $configured,
                'effective' => $effectiveValue,
                'blocked_by' => $blockedBy,
            ];
        }

        return $matrix;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, array $payload): AiAssistantSetting
    {
        $setting = $this->get();

        $allowedFields = [
            'master_enabled',
            'lab_adapter_enabled',
            'rag_enabled',
            'human_handoff_enabled',
            'learning_queue_enabled',
            'internal_canary_enabled',
            'flight_search_read_only_enabled',
        ];

        $oldValues = $this->adminValues();
        $changes = [];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $payload)) {
                $changes[$field] = (bool) $payload[$field];
            }
        }

        if (array_key_exists('audience_mode', $payload)) {
            $mode = strtolower(trim((string) $payload['audience_mode']));
            if ($mode === AiAssistantSetting::AUDIENCE_PUBLIC) {
                throw new \InvalidArgumentException('Public rollout is not authorized in this phase.');
            }
            if (! in_array($mode, [AiAssistantSetting::AUDIENCE_OFF, AiAssistantSetting::AUDIENCE_INTERNAL_CANARY], true)) {
                throw new \InvalidArgumentException('Invalid audience mode.');
            }
            $changes['audience_mode'] = $mode;
        }

        if ($changes !== []) {
            $setting->fill($changes);
            $setting->updated_by_user_id = $actor->id;
            $setting->save();
        }

        $newValues = $this->adminValues();

        foreach ($changes as $field => $newValue) {
            $oldValue = $oldValues[$field] ?? null;
            if ($oldValue === $newValue) {
                continue;
            }

            AuditLog::query()->create([
                'agency_id' => null,
                'user_id' => $actor->id,
                'action' => 'ai_assistant.settings_updated',
                'auditable_type' => AiAssistantSetting::class,
                'auditable_id' => $setting->id,
                'properties' => [
                    'setting' => $field,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                ],
            ]);
        }

        return $setting->fresh() ?? $setting;
    }

    /**
     * @return array<string, mixed>
     */
    private function bootstrapDefaults(): array
    {
        $legacyMode = strtolower(trim((string) config('ota.ai_assistant.mode', 'off')));
        $legacyOn = $legacyMode !== 'off' || (bool) config('ota.ai_assistant.enabled', false);

        return [
            'master_enabled' => $legacyOn,
            'lab_adapter_enabled' => (bool) config('ai_lab.enabled', false) || (bool) config('ai_lab.canary_only', false),
            'rag_enabled' => (bool) config('ai_lab.rag_enabled', true),
            'human_handoff_enabled' => (bool) config('ota.ai_assistant.human_handoff_enabled', true),
            'learning_queue_enabled' => (bool) config('ai_lab.learning_queue_enabled', true),
            'internal_canary_enabled' => $legacyMode === 'internal_canary',
            'flight_search_read_only_enabled' => false,
            'audience_mode' => in_array($legacyMode, [
                AiAssistantSetting::AUDIENCE_INTERNAL_CANARY,
                AiAssistantSetting::AUDIENCE_PUBLIC,
            ], true) ? $legacyMode : AiAssistantSetting::AUDIENCE_OFF,
        ];
    }

    private function fallbackModel(): AiAssistantSetting
    {
        $defaults = $this->bootstrapDefaults();

        return new AiAssistantSetting($defaults);
    }

    /**
     * @param  array<string, mixed>  $admin
     * @param  array<string, bool>  $hard
     */
    private function resolveEffectiveAudienceMode(bool $master, array $admin, array $hard): string
    {
        if (! $master) {
            return AiAssistantSetting::AUDIENCE_OFF;
        }

        $configuredMode = (string) ($admin['audience_mode'] ?? AiAssistantSetting::AUDIENCE_OFF);
        if ($configuredMode === AiAssistantSetting::AUDIENCE_PUBLIC) {
            return $hard['public'] ? AiAssistantSetting::AUDIENCE_PUBLIC : AiAssistantSetting::AUDIENCE_OFF;
        }

        if ($configuredMode === AiAssistantSetting::AUDIENCE_INTERNAL_CANARY
            && $hard['internal_canary']
            && $admin['internal_canary_enabled']) {
            return AiAssistantSetting::AUDIENCE_INTERNAL_CANARY;
        }

        if ($admin['internal_canary_enabled'] && $hard['internal_canary']) {
            return AiAssistantSetting::AUDIENCE_INTERNAL_CANARY;
        }

        return AiAssistantSetting::AUDIENCE_OFF;
    }
}
