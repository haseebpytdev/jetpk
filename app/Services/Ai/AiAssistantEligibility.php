<?php

namespace App\Services\Ai;

use App\Models\AiAssistantSetting;
use App\Models\User;
use App\Support\Staff\StaffPermission;
use Illuminate\Http\Request;

/**
 * Server-authoritative Ask JetPakistan audience gate.
 * Modes: off | internal_canary | public
 *
 * Effective mode = ENV_HARD_ALLOW ∧ ADMIN_SETTING ∧ USER_ELIGIBILITY.
 */
final class AiAssistantEligibility
{
    public const MODE_OFF = 'off';

    public const MODE_INTERNAL_CANARY = 'internal_canary';

    public const MODE_PUBLIC = 'public';

    public function __construct(
        private readonly AiAssistantSettingsService $settingsService,
    ) {}

    public function mode(): string
    {
        $effective = $this->settingsService->effective();
        $mode = (string) ($effective['audience_mode'] ?? self::MODE_OFF);

        if (! ($effective['master_enabled'] ?? false)) {
            return self::MODE_OFF;
        }

        if (! in_array($mode, [self::MODE_OFF, self::MODE_INTERNAL_CANARY, self::MODE_PUBLIC], true)) {
            return $this->legacyConfigMode();
        }

        return $mode;
    }

    public function isRuntimeOn(): bool
    {
        $effective = $this->settingsService->effective();

        return (bool) ($effective['runtime_on'] ?? false);
    }

    public function isEligible(?User $user): bool
    {
        return match ($this->mode()) {
            self::MODE_PUBLIC => true,
            self::MODE_INTERNAL_CANARY => $this->isCanaryUser($user),
            default => false,
        };
    }

    public function isEligibleRequest(Request $request): bool
    {
        $user = $request->user();

        return $this->isEligible($user instanceof User ? $user : null);
    }

    public function isCanaryUser(?User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if (method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()) {
            return true;
        }
        if (! method_exists($user, 'isStaff') || ! $user->isStaff()) {
            return false;
        }

        return $user->hasStaffPermission(StaffPermission::SupportView);
    }

    /**
     * @return array<string, mixed>
     */
    public function statusPayload(): array
    {
        $effective = $this->settingsService->effective();
        $admin = $this->settingsService->adminValues();
        $hard = $this->settingsService->envHardAllows();

        return [
            'mode' => $this->mode(),
            'runtime_on' => $this->isRuntimeOn(),
            'public_enabled' => $this->mode() === self::MODE_PUBLIC,
            'internal_canary_enabled' => $this->mode() === self::MODE_INTERNAL_CANARY,
            'language_engine' => 'hybrid_model_free',
            'local_llm_required' => false,
            'flight_tool' => (bool) ($effective['flight_search_read_only_enabled'] ?? false)
                || ((bool) config('ai_lab.shadow_flight_search', true) && ! ($effective['flight_search_read_only_enabled'] ?? false)),
            'group_tool' => (bool) config('ota.ai_assistant.groups_enabled', true),
            'knowledge' => (bool) ($effective['rag_enabled'] ?? false),
            'human_handoff' => (bool) ($effective['human_handoff_enabled'] ?? false),
            'admin' => $admin,
            'env_hard' => $hard,
            'effective' => $effective,
            'control_matrix' => $this->settingsService->controlMatrix(),
            'hard_locked_writes' => $this->settingsService->hardLockedWriteCapabilities(),
            'public_beta' => false,
        ];
    }

    private function legacyConfigMode(): string
    {
        $mode = strtolower(trim((string) config('ota.ai_assistant.mode', self::MODE_OFF)));
        if (! in_array($mode, [self::MODE_OFF, self::MODE_INTERNAL_CANARY, self::MODE_PUBLIC], true)) {
            return (bool) config('ota.ai_assistant.enabled', false)
                ? self::MODE_PUBLIC
                : self::MODE_OFF;
        }

        if ($mode === AiAssistantSetting::AUDIENCE_PUBLIC && ! (bool) config('ota.ai_assistant.hard_allow.public', false)) {
            return self::MODE_OFF;
        }

        return $mode;
    }
}
