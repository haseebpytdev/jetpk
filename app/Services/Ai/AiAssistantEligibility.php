<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Support\Staff\StaffPermission;
use Illuminate\Http\Request;

/**
 * Server-authoritative Ask JetPakistan audience gate.
 * Modes: off | internal_canary | public
 */
final class AiAssistantEligibility
{
    public const MODE_OFF = 'off';

    public const MODE_INTERNAL_CANARY = 'internal_canary';

    public const MODE_PUBLIC = 'public';

    public function mode(): string
    {
        $mode = strtolower(trim((string) config('ota.ai_assistant.mode', self::MODE_OFF)));
        if (! in_array($mode, [self::MODE_OFF, self::MODE_INTERNAL_CANARY, self::MODE_PUBLIC], true)) {
            // Legacy: enabled boolean without mode → treat as public when enabled, else off.
            return (bool) config('ota.ai_assistant.enabled', false)
                ? self::MODE_PUBLIC
                : self::MODE_OFF;
        }

        return $mode;
    }

    public function isRuntimeOn(): bool
    {
        return $this->mode() !== self::MODE_OFF;
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
        return [
            'mode' => $this->mode(),
            'runtime_on' => $this->isRuntimeOn(),
            'public_enabled' => $this->mode() === self::MODE_PUBLIC,
            'internal_canary_enabled' => $this->mode() === self::MODE_INTERNAL_CANARY,
            'language_engine' => 'hybrid_model_free',
            'local_llm_required' => false,
            'flight_tool' => (bool) config('ota.ai_assistant.flight_search_enabled', true),
            'group_tool' => (bool) config('ota.ai_assistant.groups_enabled', true),
            'knowledge' => (bool) config('ota.ai_assistant.knowledge_enabled', true),
            'human_handoff' => (bool) config('ota.ai_assistant.human_handoff_enabled', true),
        ];
    }
}
