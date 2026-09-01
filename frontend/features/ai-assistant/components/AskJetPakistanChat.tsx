"use client";

import { Button } from "@/components/ui/Button";
import { laravelApiPath } from "@/services/flight-search";
import { ensureLaravelCsrfToken } from "@/features/public-content/utils/laravel-api";
import { cn } from "@/lib/cn";
import Link from "next/link";
import { useCallback, useEffect, useId, useRef, useState } from "react";

type ChatAction = { label: string; href?: string; action?: string };
type Recommendation = {
  id: string;
  title?: string;
  subtitle?: string;
  view_and_book_url?: string;
  package_url?: string;
  results_url?: string;
  price?: number | null;
  currency?: string;
  labels?: string[];
};

type ChatMessage = {
  id: string;
  role: "user" | "assistant" | "staff" | "system";
  body: string;
  recommendations?: Recommendation[];
  actions?: ChatAction[];
};

type AskJetPakistanChatProps = {
  enabled: boolean;
};

const STORAGE_KEY = "jp_ai_conversation_id";

async function postAi(path: string, body: Record<string, unknown>) {
  const csrf = await ensureLaravelCsrfToken();
  const response = await fetch(laravelApiPath(path), {
    method: "POST",
    credentials: "include",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-Requested-With": "XMLHttpRequest",
      ...(csrf ? { "X-XSRF-TOKEN": csrf } : {}),
    },
    body: JSON.stringify(body),
  });
  const json = (await response.json().catch(() => ({}))) as Record<string, unknown>;
  return { response, json };
}

export function AskJetPakistanChat({ enabled }: AskJetPakistanChatProps) {
  const titleId = useId();
  const panelRef = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLDivElement>(null);
  const [open, setOpen] = useState(false);
  const [input, setInput] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const lastPollId = useRef(0);

  const scrollToEnd = useCallback(() => {
    const el = listRef.current;
    if (el) el.scrollTop = el.scrollHeight;
  }, []);

  useEffect(() => {
    if (!enabled) return;
    try {
      const stored = sessionStorage.getItem(STORAGE_KEY);
      if (stored) setConversationId(stored);
    } catch {
      /* ignore */
    }
  }, [enabled]);

  useEffect(() => {
    if (!enabled) return;
    const syncHash = () => {
      if (typeof window === "undefined") return;
      if (window.location.hash === "#ask-jetpakistan") {
        setOpen(true);
      }
    };
    syncHash();
    window.addEventListener("hashchange", syncHash);
    return () => window.removeEventListener("hashchange", syncHash);
  }, [enabled]);

  useEffect(() => {
    if (open) scrollToEnd();
  }, [messages, open, scrollToEnd]);

  useEffect(() => {
    if (!enabled || !open || !conversationId) return;
    let cancelled = false;
    const tick = async () => {
      try {
        const url = laravelApiPath(
          `/api/public/ai/messages?conversation_id=${encodeURIComponent(conversationId)}&since_id=${lastPollId.current}`,
        );
        const response = await fetch(url, {
          credentials: "include",
          headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
        });
        if (!response.ok || cancelled) return;
        const json = (await response.json()) as {
          messages?: Array<{ id: number; role: string; body: string; meta?: { recommendations?: Recommendation[] } }>;
        };
        const incoming = json.messages ?? [];
        if (incoming.length === 0) return;
        setMessages((prev) => {
          const known = new Set(prev.map((m) => m.id));
          const next = [...prev];
          for (const m of incoming) {
            const id = String(m.id);
            lastPollId.current = Math.max(lastPollId.current, m.id);
            if (known.has(id)) continue;
            if (m.role === "user") continue;
            next.push({
              id,
              role: (m.role as ChatMessage["role"]) || "assistant",
              body: m.body,
              recommendations: m.meta?.recommendations,
            });
          }
          return next;
        });
      } catch {
        /* soft poll */
      }
    };
    const timer = window.setInterval(tick, 4000);
    return () => {
      cancelled = true;
      window.clearInterval(timer);
    };
  }, [enabled, open, conversationId]);

  if (!enabled) return null;

  const close = () => {
    setOpen(false);
    if (typeof window !== "undefined" && window.location.hash === "#ask-jetpakistan") {
      history.replaceState(null, "", window.location.pathname + window.location.search);
    }
  };

  const appendAssistant = (json: Record<string, unknown>) => {
    const cid = typeof json.conversation_id === "string" ? json.conversation_id : null;
    if (cid) {
      setConversationId(cid);
      try {
        sessionStorage.setItem(STORAGE_KEY, cid);
      } catch {
        /* ignore */
      }
    }
    const body =
      typeof json.message === "string"
        ? json.message
        : "Something went wrong. Please try again.";
    setMessages((prev) => [
      ...prev,
      {
        id: `a-${Date.now()}`,
        role: "assistant",
        body,
        recommendations: Array.isArray(json.recommendations)
          ? (json.recommendations as Recommendation[])
          : undefined,
        actions: Array.isArray(json.actions) ? (json.actions as ChatAction[]) : undefined,
      },
    ]);
  };

  const send = async (text: string) => {
    const trimmed = text.trim();
    if (!trimmed || busy) return;
    setBusy(true);
    setError(null);
    setMessages((prev) => [...prev, { id: `u-${Date.now()}`, role: "user", body: trimmed }]);
    setInput("");
    try {
      const { response, json } = await postAi("/api/public/ai/chat", {
        message: trimmed,
        conversation_id: conversationId,
      });
      if (response.status === 503 || json.status === "unavailable") {
        appendAssistant(json);
        return;
      }
      if (!response.ok && response.status !== 200) {
        setError(typeof json.message === "string" ? json.message : "Request failed. Retry.");
        appendAssistant(json);
        return;
      }
      appendAssistant(json);
    } catch {
      setError("Network error. Please retry.");
    } finally {
      setBusy(false);
    }
  };

  const handoff = async () => {
    if (!conversationId) {
      await send("Talk to support");
      return;
    }
    setBusy(true);
    try {
      const { json } = await postAi("/api/public/ai/handoff", { conversation_id: conversationId });
      appendAssistant(json);
    } catch {
      setError("Could not reach support queue.");
    } finally {
      setBusy(false);
    }
  };

  const clearChat = async () => {
    setBusy(true);
    try {
      const { json } = await postAi("/api/public/ai/clear", {
        conversation_id: conversationId,
      });
      const cid = typeof json.conversation_id === "string" ? json.conversation_id : null;
      setConversationId(cid);
      if (cid) {
        try {
          sessionStorage.setItem(STORAGE_KEY, cid);
        } catch {
          /* ignore */
        }
      }
      lastPollId.current = 0;
      setMessages([
        {
          id: `a-${Date.now()}`,
          role: "assistant",
          body: "New chat started. Ask about flights, groups, payments, or talk to support.",
          actions: [
            { label: "Search Flights", href: "/#flight-search" },
            { label: "Browse Groups", href: "/groups" },
            { label: "Talk to Support", action: "handoff" },
          ],
        },
      ]);
    } catch {
      setError("Could not clear chat.");
    } finally {
      setBusy(false);
    }
  };

  const onAction = (action: ChatAction) => {
    if (action.action === "handoff") {
      void handoff();
      return;
    }
    if (action.href) {
      window.location.href = action.href;
    }
  };

  return (
    <>
      {!open ? null : (
        <div
          ref={panelRef}
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          data-testid="ask-jetpakistan-panel"
          className={cn(
            "fixed z-50 flex w-[min(24rem,calc(100vw-1.5rem))] flex-col overflow-hidden rounded-jp-lg border border-jp-border bg-jp-surface shadow-jp-md",
            "right-[max(0.75rem,env(safe-area-inset-right))] bottom-[max(5.5rem,calc(env(safe-area-inset-bottom)+4.5rem))] xl:bottom-[max(1.25rem,env(safe-area-inset-bottom))] xl:right-[max(1.25rem,env(safe-area-inset-right))]",
            "max-h-[min(70vh,32rem)]",
          )}
        >
          <div className="flex items-center justify-between gap-2 border-b border-jp-border px-3 py-2">
            <h2 id={titleId} className="text-jp-sm font-semibold text-jp-text">
              Ask JetPakistan
            </h2>
            <div className="flex items-center gap-1">
              <Button type="button" variant="ghost" className="min-h-9 px-2 text-jp-xs" onClick={() => void clearChat()}>
                Clear
              </Button>
              <Button type="button" variant="ghost" className="min-h-9 px-2 text-jp-xs" onClick={close} aria-label="Close chat">
                Close
              </Button>
            </div>
          </div>

          <div ref={listRef} className="flex-1 space-y-3 overflow-y-auto px-3 py-3" data-testid="ask-jetpakistan-messages">
            {messages.length === 0 ? (
              <p className="text-jp-sm text-jp-muted">
                Ask for flights (e.g. LHE to DXB), groups, booking or payment help — or talk to support.
              </p>
            ) : null}
            {messages.map((m) => (
              <div
                key={m.id}
                className={cn(
                  "rounded-jp-md px-3 py-2 text-jp-sm",
                  m.role === "user" ? "ml-6 bg-jp-brand text-white" : "mr-4 bg-jp-brand-soft text-jp-text",
                )}
              >
                <p className="whitespace-pre-wrap">{m.body}</p>
                {m.recommendations && m.recommendations.length > 0 ? (
                  <ul className="mt-2 space-y-2">
                    {m.recommendations.map((r) => (
                      <li key={r.id} className="rounded-jp-md border border-jp-border bg-jp-surface p-2">
                        <div className="font-semibold">{r.title}</div>
                        {r.subtitle ? <div className="text-jp-xs text-jp-muted">{r.subtitle}</div> : null}
                        {r.view_and_book_url ? (
                          <Link
                            href={r.view_and_book_url}
                            className="mt-1 inline-flex text-jp-xs font-semibold text-jp-brand underline focus-visible:outline-none focus-visible:shadow-jp-focus"
                          >
                            View &amp; Book
                          </Link>
                        ) : null}
                      </li>
                    ))}
                  </ul>
                ) : null}
                {m.actions && m.actions.length > 0 ? (
                  <div className="mt-2 flex flex-wrap gap-1">
                    {m.actions.map((a) =>
                      a.href && !a.action ? (
                        <Link
                          key={a.label}
                          href={a.href}
                          className="rounded-jp-md border border-jp-border bg-jp-surface px-2 py-1 text-jp-xs font-semibold text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
                        >
                          {a.label}
                        </Link>
                      ) : (
                        <button
                          key={a.label}
                          type="button"
                          onClick={() => onAction(a)}
                          className="rounded-jp-md border border-jp-border bg-jp-surface px-2 py-1 text-jp-xs font-semibold text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
                        >
                          {a.label}
                        </button>
                      ),
                    )}
                  </div>
                ) : null}
              </div>
            ))}
            {busy ? (
              <p className="text-jp-xs text-jp-muted" aria-live="polite">
                Thinking…
              </p>
            ) : null}
            {error ? (
              <p className="text-jp-xs text-red-700" role="alert">
                {error}{" "}
                <button type="button" className="underline" onClick={() => setError(null)}>
                  Dismiss
                </button>
              </p>
            ) : null}
          </div>

          <div className="border-t border-jp-border p-2">
            <div className="mb-2 flex flex-wrap gap-1">
              <button
                type="button"
                className="rounded-jp-md border border-jp-border px-2 py-1 text-jp-xs font-semibold"
                onClick={() => void send("Find flights Lahore to Dubai")}
              >
                Find Flights
              </button>
              <button
                type="button"
                className="rounded-jp-md border border-jp-border px-2 py-1 text-jp-xs font-semibold"
                onClick={() => void send("Find Groups for Dubai")}
              >
                Find Groups
              </button>
              <button
                type="button"
                className="rounded-jp-md border border-jp-border px-2 py-1 text-jp-xs font-semibold"
                onClick={() => void send("How does booking work")}
              >
                Booking Help
              </button>
              <button
                type="button"
                className="rounded-jp-md border border-jp-border px-2 py-1 text-jp-xs font-semibold"
                onClick={() => void send("payment deadline help")}
              >
                Payment Help
              </button>
              <button
                type="button"
                className="rounded-jp-md border border-jp-border px-2 py-1 text-jp-xs font-semibold"
                onClick={() => void send("Saved Travelers help")}
              >
                Saved Travelers
              </button>
              <button
                type="button"
                className="rounded-jp-md border border-jp-border px-2 py-1 text-jp-xs font-semibold"
                onClick={() => void handoff()}
              >
                Talk to Support
              </button>
            </div>
            <form
              className="flex gap-2"
              onSubmit={(e) => {
                e.preventDefault();
                void send(input);
              }}
            >
              <label className="sr-only" htmlFor="ask-jp-input">
                Message
              </label>
              <input
                id="ask-jp-input"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                maxLength={2000}
                placeholder="Ask about travel…"
                className="min-h-11 flex-1 rounded-jp-md border border-jp-border bg-jp-surface px-3 text-jp-sm text-jp-text focus-visible:outline-none focus-visible:shadow-jp-focus"
                disabled={busy}
              />
              <Button type="submit" variant="primary" className="min-h-11 shrink-0" disabled={busy || !input.trim()}>
                Send
              </Button>
            </form>
          </div>
        </div>
      )}
    </>
  );
}
