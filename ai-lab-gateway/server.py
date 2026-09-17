#!/usr/bin/env python3
"""Localhost-only AI lab consultant gateway for JetPakistan shadow integration."""

from __future__ import annotations

import json
import os
import sys
from datetime import date
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from typing import Any

LAB_ROOT = os.environ.get("OTA_AI_LAB_REPO_PATH", os.path.join(os.path.dirname(__file__), "..", "tmp", "ai-lab"))
LAB_SHA = "7977f19f5e35eedfca27504a53cfdff78e35f0ba"
BIND_HOST = "127.0.0.1"
BIND_PORT = int(os.environ.get("OTA_AI_LAB_GATEWAY_PORT", "8765"))

sys.path.insert(0, LAB_ROOT)

from app.pipeline import run_consultant_turn  # noqa: E402
from app.policy.handoff import detect_unsupported_intent  # noqa: E402
from app.rag.pipeline import answer_policy_question  # noqa: E402
from app.rag.grounding import is_live_data_question  # noqa: E402
from app.state import TravelConversationState  # noqa: E402
from app.learning.enqueue import build_learning_event  # noqa: E402
from app.learning.queue_schema import FailureCategory  # noqa: E402

POLICY_MARKERS = (
    "baggage", "booking", "payment", "refund process", "cancellation",
    "how does", "what is", "policy", "check-in", "check in", "saved traveler",
)


def is_policy_question(message: str) -> bool:
    lower = message.lower()
    if detect_unsupported_intent(message):
        return False
    if is_live_data_question(message):
        return True
    if any(m in lower for m in POLICY_MARKERS):
        return True
    return False


def state_from_dict(data: dict[str, Any] | None) -> TravelConversationState | None:
    if not data:
        return None
    try:
        return TravelConversationState.model_validate(data)
    except Exception:
        return None


def map_dialog_to_confirmation(dialog_state: str) -> str:
    mapping = {
        "AWAITING_CONFIRMATION": "PROPOSED",
        "CONFIRMED": "CONFIRMED",
        "EXECUTING_TOOL": "CONFIRMED",
        "RESULTS": "CONFIRMED",
        "COLLECTING": "NONE",
    }
    return mapping.get(dialog_state, "NONE")


def build_action(state: TravelConversationState) -> dict[str, Any]:
    if state.tool_executed and state.tool_result:
        return {
            "kind": "SHADOW_FLIGHT_SEARCH",
            "payload": state.tool_result,
        }
    if state.handoff_consent is True and state.handoff_payload:
        return {
            "kind": "MOCK_HANDOFF",
            "payload": state.handoff_payload,
        }
    return {"kind": "NONE", "payload": {}}


def map_failure_category(code: str | None) -> FailureCategory | None:
    if code is None:
        return None
    mapping = {
        "RAG_NO_SOURCE": FailureCategory.RAG_NO_SOURCE,
        "RAG_CONFLICT": FailureCategory.RAG_CONFLICT,
        "LIVE_DATA_FROM_RAG": FailureCategory.RAG_NO_SOURCE,
        "RAG_INJECTION": FailureCategory.OTHER,
    }
    return mapping.get(code)


def handle_rag_turn(message: str, conversation_id: str, prior: TravelConversationState | None) -> dict[str, Any]:
    rag = answer_policy_question(message)
    action = {"kind": "RAG_ANSWER" if rag.get("grounded") and not rag.get("no_source") else "NONE", "payload": {}}
    failure = None
    if rag.get("live_data_refused"):
        failure = "LIVE_DATA_FROM_RAG"
    elif rag.get("no_source"):
        failure = "RAG_NO_SOURCE"
    elif rag.get("injection_blocked"):
        failure = "RAG_INJECTION"

    lab_state = prior.model_dump() if prior else TravelConversationState(conversation_id=conversation_id).model_dump()
    lab_state["last_user_message"] = message
    lab_state["reply"] = rag["answer"]

    learning = build_learning_event(
        TravelConversationState.model_validate(lab_state),
        conversation_id=conversation_id,
        failure_category=map_failure_category(failure),
    )

    return {
        "contract_version": "v1",
        "assistant_message": rag["answer"],
        "status": "ok" if rag.get("grounded") else "refused",
        "mode": "LAB_CONSULTANT_V1",
        "parser": {"intent": "knowledge", "slots": {}},
        "confirmation": {"state": "NONE", "snapshot": {}, "snapshot_hash": None},
        "action": action,
        "rag": {
            "hits": rag.get("evidence", []),
            "blocked_live_data": bool(rag.get("live_data_refused")),
            "no_source": bool(rag.get("no_source")),
            "injection_blocked": bool(rag.get("injection_blocked")),
        },
        "recommendations": [],
        "meta": {"pipeline": ["04B"], "lab_sha": LAB_SHA},
        "learning_event": learning.model_dump(),
        "lab_state": lab_state,
        "failure_event": failure,
    }


def handle_consultant_turn(
    message: str,
    conversation_id: str,
    prior: TravelConversationState | None,
    authenticated_user_id: str | None,
) -> dict[str, Any]:
    state = run_consultant_turn(
        message,
        reference_date=date.today().isoformat(),
        prior=prior,
        llm_payload=None,
        authenticated_user_id=authenticated_user_id,
    )

    dialog = state.dialog_state
    confirmation_state = map_dialog_to_confirmation(dialog)
    snap = state.confirmation_snapshot or {}
    token = state.confirmation_token

    status = "ok"
    if state.clarification_needed or state.missing_required_fields:
        status = "clarify"
    elif dialog == "AWAITING_CONFIRMATION":
        status = "needs_confirmation"
    elif state.unsupported_capability:
        status = "clarify"

    learning = build_learning_event(state, conversation_id=conversation_id, authenticated_user_id=authenticated_user_id)

    origin = state.origin or ""
    destination = state.destination or ""
    route_visible = bool(origin and destination) or bool(state.reply and (origin or destination))

    return {
        "contract_version": "v1",
        "assistant_message": state.reply or "How can I help with your travel plans?",
        "status": status,
        "mode": "LAB_CONSULTANT_V1",
        "parser": {
            "intent": state.intent,
            "slots": {
                "origin": state.origin,
                "destination": state.destination,
                "departure_date": state.departure_date,
                "return_date": state.return_date,
                "trip_type": state.trip_type,
            },
            "residual_case": None,
            "route_visible": route_visible,
        },
        "confirmation": {
            "state": confirmation_state,
            "proposed_action": "SHADOW_FLIGHT_SEARCH" if dialog == "AWAITING_CONFIRMATION" else None,
            "snapshot": snap,
            "snapshot_hash": token,
        },
        "action": build_action(state),
        "rag": {"hits": [], "blocked_live_data": False},
        "recommendations": [],
        "meta": {"pipeline": ["04A", "lab03", "04B"], "lab_sha": LAB_SHA},
        "learning_event": learning.model_dump(),
        "lab_state": state.model_dump(),
        "failure_event": None,
    }


def handle_turn(payload: dict[str, Any]) -> dict[str, Any]:
    message = str(payload.get("message", "")).strip()
    conversation_id = str(payload.get("conversation_id", ""))
    user_ctx = payload.get("user_context") or {}
    authenticated_user_id = user_ctx.get("user_id")
    state_wrapper = payload.get("state") or {}
    prior = state_from_dict(state_wrapper.get("lab") if isinstance(state_wrapper, dict) else None)

    if is_policy_question(message):
        return handle_rag_turn(message, conversation_id, prior)

    return handle_consultant_turn(message, conversation_id, prior, authenticated_user_id)


class GatewayHandler(BaseHTTPRequestHandler):
    def log_message(self, format: str, *args: Any) -> None:  # noqa: A003
        return

    def _send_json(self, status: int, body: dict[str, Any]) -> None:
        data = json.dumps(body).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self) -> None:  # noqa: N802
        if self.path.rstrip("/") == "/health":
            self._send_json(200, {"ok": True, "lab_sha": LAB_SHA, "contract": "v1"})
            return
        self._send_json(404, {"ok": False, "error": "not_found"})

    def do_POST(self) -> None:  # noqa: N802
        if self.path.rstrip("/") != "/v1/consultant/turn":
            self._send_json(404, {"ok": False, "error": "not_found"})
            return

        length = int(self.headers.get("Content-Length", "0"))
        raw = self.rfile.read(length) if length > 0 else b"{}"
        try:
            payload = json.loads(raw.decode("utf-8"))
        except json.JSONDecodeError:
            self._send_json(400, {"ok": False, "error": "malformed_json"})
            return

        try:
            result = handle_turn(payload)
            self._send_json(200, result)
        except Exception as exc:  # noqa: BLE001
            self._send_json(503, {
                "contract_version": "v1",
                "assistant_message": "AI assistant is temporarily unavailable. Please try again shortly.",
                "status": "degraded",
                "mode": "LAB_CONSULTANT_V1",
                "failure_event": "MODEL_TIMEOUT",
                "meta": {"error": str(exc)},
            })


def main() -> None:
    server = ThreadingHTTPServer((BIND_HOST, BIND_PORT), GatewayHandler)
    print(f"AI lab gateway listening on http://{BIND_HOST}:{BIND_PORT} (lab={LAB_ROOT})")
    server.serve_forever()


if __name__ == "__main__":
    main()
