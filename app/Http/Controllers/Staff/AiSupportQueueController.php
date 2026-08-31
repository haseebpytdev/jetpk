<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiHandoffAudit;
use App\Models\AiMessage;
use App\Support\Staff\StaffPermission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Minimal staff queue for AI chat handoffs (not SupportTicket live chat).
 */
class AiSupportQueueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSupportView($request);

        $conversations = AiConversation::query()
            ->whereIn('state', [
                AiConversation::STATE_WAITING_FOR_HUMAN,
                AiConversation::STATE_HUMAN_ACTIVE,
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('dashboard.staff.support.ai-queue.index', compact('conversations'));
    }

    public function show(Request $request, string $publicId): View
    {
        $this->authorizeSupportView($request);
        $conversation = $this->findOrFail($publicId);
        $messages = $conversation->messages()->orderBy('id')->limit(200)->get();

        return view('dashboard.staff.support.ai-queue.show', compact('conversation', 'messages'));
    }

    public function takeover(Request $request, string $publicId): RedirectResponse
    {
        $this->authorizeSupportReply($request);
        $conversation = $this->findOrFail($publicId);
        $from = $conversation->state;
        $conversation->forceFill([
            'state' => AiConversation::STATE_HUMAN_ACTIVE,
            'taken_over_by_user_id' => $request->user()?->id,
            'taken_over_at' => now(),
        ])->save();

        AiHandoffAudit::query()->create([
            'ai_conversation_id' => $conversation->id,
            'staff_user_id' => $request->user()?->id,
            'from_state' => $from,
            'to_state' => AiConversation::STATE_HUMAN_ACTIVE,
            'reason' => 'staff_takeover',
        ]);

        $conversation->messages()->create([
            'role' => 'system',
            'body' => 'A support agent joined the conversation.',
            'meta' => ['event' => 'takeover'],
        ]);

        return redirect()
            ->route('staff.support.ai-queue.show', $conversation->public_id)
            ->with('status', 'Conversation taken over.');
    }

    public function reply(Request $request, string $publicId): RedirectResponse
    {
        $this->authorizeSupportReply($request);
        $conversation = $this->findOrFail($publicId);
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);
        $body = trim(strip_tags($data['body']));

        if ($conversation->state === AiConversation::STATE_WAITING_FOR_HUMAN) {
            $from = $conversation->state;
            $conversation->forceFill([
                'state' => AiConversation::STATE_HUMAN_ACTIVE,
                'taken_over_by_user_id' => $request->user()?->id,
                'taken_over_at' => now(),
            ])->save();
            AiHandoffAudit::query()->create([
                'ai_conversation_id' => $conversation->id,
                'staff_user_id' => $request->user()?->id,
                'from_state' => $from,
                'to_state' => AiConversation::STATE_HUMAN_ACTIVE,
                'reason' => 'staff_reply_takeover',
            ]);
        }

        AiMessage::query()->create([
            'ai_conversation_id' => $conversation->id,
            'role' => 'staff',
            'body' => $body,
            'meta' => ['staff_user_id' => $request->user()?->id],
        ]);
        $conversation->forceFill(['last_message_at' => now()])->save();

        return redirect()
            ->route('staff.support.ai-queue.show', $conversation->public_id)
            ->with('status', 'Reply sent.');
    }

    public function returnToAi(Request $request, string $publicId): RedirectResponse
    {
        $this->authorizeSupportReply($request);
        $conversation = $this->findOrFail($publicId);
        $from = $conversation->state;
        $conversation->forceFill([
            'state' => AiConversation::STATE_AI_ACTIVE,
            'taken_over_by_user_id' => null,
            'taken_over_at' => null,
        ])->save();

        AiHandoffAudit::query()->create([
            'ai_conversation_id' => $conversation->id,
            'staff_user_id' => $request->user()?->id,
            'from_state' => $from,
            'to_state' => AiConversation::STATE_AI_ACTIVE,
            'reason' => 'return_to_ai',
        ]);

        $conversation->messages()->create([
            'role' => 'system',
            'body' => 'Returned to AI assistant.',
            'meta' => ['event' => 'return_to_ai'],
        ]);

        return redirect()
            ->route('staff.support.ai-queue.show', $conversation->public_id)
            ->with('status', 'Returned to AI.');
    }

    private function findOrFail(string $publicId): AiConversation
    {
        return AiConversation::query()->where('public_id', $publicId)->firstOrFail();
    }

    private function authorizeSupportView(Request $request): void
    {
        abort_unless($request->user() !== null, 403);
        // Staff portal middleware already gates portal; fine-grained key when present.
        $perms = data_get($request->user()->meta ?? [], 'staff_permissions');
        if (is_array($perms) && $perms !== [] && ! in_array(StaffPermission::SupportView, $perms, true)
            && ! in_array(StaffPermission::PresetSupport, $perms, true)
            && ! in_array(StaffPermission::PresetManager, $perms, true)) {
            abort(403);
        }
    }

    private function authorizeSupportReply(Request $request): void
    {
        abort_unless($request->user() !== null, 403);
        $perms = data_get($request->user()->meta ?? [], 'staff_permissions');
        if (is_array($perms) && $perms !== [] && ! in_array(StaffPermission::SupportReply, $perms, true)
            && ! in_array(StaffPermission::PresetSupport, $perms, true)
            && ! in_array(StaffPermission::PresetManager, $perms, true)) {
            abort(403);
        }
    }
}
