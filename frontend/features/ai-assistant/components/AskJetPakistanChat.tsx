"use client";

import { usePublicFloatingLayoutOptional } from "@/features/public-floating/PublicFloatingLayoutProvider";
import { ensureLaravelCsrfToken } from "@/features/public-content/utils/laravel-api";
import { UI_ICON_ACTION_CLASS, UI_ICON_FAB_CLASS, UI_ICON_STROKE } from "@/lib/ui-icon";
import { laravelApiPath } from "@/services/flight-search";
import {
  ArrowRight,
  CalendarDays,
  CreditCard,
  Headphones,
  MessageCircle,
  Minus,
  MoreHorizontal,
  Plane,
  Send,
  UserRound,
  UsersRound,
  X,
} from "lucide-react";
import Link from "next/link";
import {
  useCallback,
  useEffect,
  useId,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";
import styles from "./AskJetPakistanChat.module.css";

type ChatAction = {
  label: string;
  href?: string;
  action?: string;
};

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

type QuickAction = {
  id: string;
  title: string;
  description: string;
  icon: ReactNode;
  message?: string;
  href?: string;
};

const STORAGE_KEY = "jp_ai_conversation_id";

const QUICK_ACTIONS: QuickAction[] = [
  {
    id: "flights",
    title: "Find Flights",
    description: "Search fares to your destination",
    message: "Find flights Lahore to Dubai",
    icon: <Plane className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
  },
  {
    id: "groups",
    title: "Find Groups",
    description: "Group bookings and special fares",
    message: "Find Groups for Dubai",
    icon: <UsersRound className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
  },
  {
    id: "booking",
    title: "Booking Help",
    description: "Manage or understand a booking",
    message: "How does booking work",
    icon: <CalendarDays className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
  },
  {
    id: "payment",
    title: "Payment Help",
    description: "Payments, refunds and invoices",
    message: "Payment help",
    icon: <CreditCard className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
  },
  {
    id: "travelers",
    title: "Saved Travelers",
    description: "Manage your traveler profiles",
    message: "Saved Travelers help",
    icon: <UserRound className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
  },
  {
    id: "support",
    title: "Talk to Support",
    description: "Open the JetPakistan support centre",
    href: "/support",
    icon: <Headphones className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />,
  },
];

const POPULAR_QUESTIONS = [
  "Baggage allowance",
  "Change my flight",
  "Check flight status",
  "Visa information",
  "Refund process",
];

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
  const listRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const floatingLayout = usePublicFloatingLayoutOptional();

  const [open, setOpen] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
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

  const clearHash = useCallback(() => {
    if (typeof window === "undefined") return;
    if (window.location.hash === "#ask-jetpakistan") {
      history.replaceState(null, "", window.location.pathname + window.location.search);
    }
  }, []);

  const close = useCallback(() => {
    setOpen(false);
    setMenuOpen(false);
    floatingLayout?.setAskOpen(false);
    clearHash();
  }, [clearHash, floatingLayout]);

  const openPanel = useCallback(() => {
    setOpen(true);
    floatingLayout?.setAskOpen(true);
  }, [floatingLayout]);

  useEffect(() => {
    floatingLayout?.setAskOpen(open);
  }, [floatingLayout, open]);

  useEffect(() => {
    if (!enabled) return;

    try {
      const stored = sessionStorage.getItem(STORAGE_KEY);
      if (stored) setConversationId(stored);
    } catch {
      /* session storage is best-effort */
    }
  }, [enabled]);

  useEffect(() => {
    if (!enabled) return;

    const syncHash = () => {
      if (typeof window !== "undefined" && window.location.hash === "#ask-jetpakistan") {
        openPanel();
      }
    };

    syncHash();
    window.addEventListener("hashchange", syncHash);
    return () => window.removeEventListener("hashchange", syncHash);
  }, [enabled]);

  useEffect(() => {
    if (!open) return;

    scrollToEnd();
    window.setTimeout(() => inputRef.current?.focus(), 80);
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
          headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
        });

        if (!response.ok || cancelled) return;

        const json = (await response.json()) as {
          messages?: Array<{
            id: number;
            role: string;
            body: string;
            meta?: {
              recommendations?: Recommendation[];
              actions?: ChatAction[];
            };
          }>;
        };

        const incoming = json.messages ?? [];
        if (incoming.length === 0) return;

        setMessages((previous) => {
          const known = new Set(previous.map((message) => message.id));
          const next = [...previous];

          for (const message of incoming) {
            const id = String(message.id);
            lastPollId.current = Math.max(lastPollId.current, message.id);

            if (known.has(id) || message.role === "user") continue;

            const duplicateBody = next.some(
              (existing) =>
                existing.role === "assistant" && existing.body === message.body,
            );
            if (duplicateBody) continue;

            next.push({
              id,
              role: (message.role as ChatMessage["role"]) || "assistant",
              body: message.body,
              recommendations: message.meta?.recommendations,
              actions: message.meta?.actions,
            });
          }

          return next;
        });
      } catch {
        /* polling is intentionally soft-fail */
      }
    };

    const timer = window.setInterval(tick, 4000);

    return () => {
      cancelled = true;
      window.clearInterval(timer);
    };
  }, [conversationId, enabled, open]);

  useEffect(() => {
    if (!enabled || !open) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") {
        event.preventDefault();
        close();
      }
    };

    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [close, enabled, open]);

  if (!enabled) return null;

  const appendAssistant = (json: Record<string, unknown>) => {
    const cid = typeof json.conversation_id === "string" ? json.conversation_id : null;

    if (cid) {
      setConversationId(cid);
      try {
        sessionStorage.setItem(STORAGE_KEY, cid);
      } catch {
        /* best-effort */
      }
    }

    const body =
      typeof json.message === "string"
        ? json.message
        : "Something went wrong. Please try again.";

    const serverId =
      typeof json.message_id === "number"
        ? String(json.message_id)
        : typeof json.message_id === "string" && json.message_id !== ""
          ? json.message_id
          : `a-${Date.now()}`;

    if (typeof json.message_id === "number") {
      lastPollId.current = Math.max(lastPollId.current, json.message_id);
    }

    setMessages((previous) => {
      if (previous.some((message) => message.id === serverId)) {
        return previous;
      }

      const duplicateBody = previous.some(
        (message) => message.role === "assistant" && message.body === body,
      );
      if (duplicateBody) {
        return previous;
      }

      return [
        ...previous,
        {
          id: serverId,
          role: "assistant",
          body,
          recommendations: Array.isArray(json.recommendations)
            ? (json.recommendations as Recommendation[])
            : undefined,
          actions: Array.isArray(json.actions)
            ? (json.actions as ChatAction[])
            : undefined,
        },
      ];
    });
  };

  const send = async (text: string) => {
    const trimmed = text.trim();
    if (!trimmed || busy) return;

    setBusy(true);
    setError(null);
    setMessages((previous) => [
      ...previous,
      {
        id: `u-${Date.now()}`,
        role: "user",
        body: trimmed,
      },
    ]);
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

      if (!response.ok) {
        setError(
          typeof json.message === "string"
            ? json.message
            : "Request failed. Please retry.",
        );
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
    setError(null);

    try {
      const { json } = await postAi("/api/public/ai/handoff", {
        conversation_id: conversationId,
      });
      appendAssistant(json);
    } catch {
      setError("Could not reach the support queue.");
    } finally {
      setBusy(false);
    }
  };

  const clearChat = async () => {
    setMenuOpen(false);
    setBusy(true);
    setError(null);

    try {
      const { json } = await postAi("/api/public/ai/clear", {
        conversation_id: conversationId,
      });

      const cid =
        typeof json.conversation_id === "string" ? json.conversation_id : null;

      setConversationId(cid);

      if (cid) {
        try {
          sessionStorage.setItem(STORAGE_KEY, cid);
        } catch {
          /* best-effort */
        }
      } else {
        try {
          sessionStorage.removeItem(STORAGE_KEY);
        } catch {
          /* best-effort */
        }
      }

      lastPollId.current = 0;
      setMessages([]);
    } catch {
      setError("Could not clear the conversation.");
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
      window.location.assign(action.href);
    }
  };

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    void send(input);
  };

  const runQuickAction = (action: QuickAction) => {
    if (action.href) {
      window.location.assign(action.href);
      return;
    }

    if (action.message) {
      void send(action.message);
    }
  };

  return (
    <>
      {!open ? (
        <div className={styles.fabWrap}>
          <span className={styles.fabTooltip} role="tooltip">
            Ask JetPakistan
            <small>Get instant travel help</small>
          </span>
          <button
            type="button"
            data-testid="ask-jetpakistan-fab"
            aria-label="Ask JetPakistan"
            onClick={openPanel}
            className={styles.fab}
          >
            <MessageCircle className={UI_ICON_FAB_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
          </button>
        </div>
      ) : null}

      {open ? (
        <section
          role="dialog"
          aria-modal="true"
          aria-labelledby={titleId}
          data-testid="ask-jetpakistan-panel"
          className={styles.panel}
        >
          <header className={styles.header}>
            <div className={styles.identity}>
              <div className={styles.avatar} aria-hidden="true">
                <Plane className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
              </div>

              <div className={styles.identityCopy}>
                <h2 id={titleId}>Ask JetPakistan</h2>
                <p>
                  <span className={styles.onlineDot} aria-hidden="true" />
                  Online <span aria-hidden="true">•</span> Your AI travel assistant
                </p>
              </div>
            </div>

            <div className={styles.headerActions}>
              <div className={styles.menuWrap}>
                <button
                  type="button"
                  className={styles.headerIconButton}
                  aria-label="Chat options"
                  aria-haspopup="menu"
                  aria-expanded={menuOpen}
                  onClick={() => setMenuOpen((value) => !value)}
                >
                  <MoreHorizontal className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
                </button>

                {menuOpen ? (
                  <div className={styles.headerMenu} role="menu">
                    <button
                      type="button"
                      role="menuitem"
                      onClick={() => void clearChat()}
                      disabled={busy}
                    >
                      Clear conversation
                    </button>
                    <Link href="/support" role="menuitem" onClick={() => setMenuOpen(false)}>
                      Open support centre
                    </Link>
                  </div>
                ) : null}
              </div>

              <button
                type="button"
                className={styles.headerIconButton}
                aria-label="Minimize Ask JetPakistan"
                onClick={close}
              >
                <Minus className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
              </button>

              <button
                type="button"
                className={styles.headerIconButton}
                aria-label="Close Ask JetPakistan"
                onClick={close}
              >
                <X className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
              </button>
            </div>
          </header>

          <div
            ref={listRef}
            className={styles.scrollArea}
            data-testid="ask-jetpakistan-messages"
          >
            {messages.length === 0 ? (
              <div className={styles.onboarding}>
                <div className={styles.assistantRow}>
                  <div className={styles.messageAvatar} aria-hidden="true">
                    <Plane className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
                  </div>
                  <div className={`${styles.messageBubble} ${styles.assistantBubble}`}>
                    <strong>Hi! I&apos;m Ask JetPakistan</strong>
                    <p>
                      I can help you find flights, check bookings, manage payments,
                      or answer travel questions.
                    </p>
                    <p>How can I help you today?</p>
                  </div>
                </div>

                <div className={styles.quickGrid} aria-label="Quick actions">
                  {QUICK_ACTIONS.map((action) => (
                    <button
                      key={action.id}
                      type="button"
                      className={styles.quickCard}
                      onClick={() => runQuickAction(action)}
                      disabled={busy}
                    >
                      <span className={styles.quickIcon} aria-hidden="true">
                        {action.icon}
                      </span>
                      <span className={styles.quickCopy}>
                        <strong>{action.title}</strong>
                        <small>{action.description}</small>
                      </span>
                      <ArrowRight className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
                    </button>
                  ))}
                </div>

                <div className={styles.popular}>
                  <div className={styles.sectionLabel}>
                    <span>Popular questions</span>
                    <i />
                  </div>
                  <div className={styles.chips}>
                    {POPULAR_QUESTIONS.map((question) => (
                      <button
                        key={question}
                        type="button"
                        onClick={() => void send(question)}
                        disabled={busy}
                      >
                        {question}
                      </button>
                    ))}
                    <button
                      type="button"
                      onClick={() => void handoff()}
                      disabled={busy}
                    >
                      Talk to a person
                    </button>
                  </div>
                </div>
              </div>
            ) : null}

            {messages.map((message) => (
              <MessageItem
                key={message.id}
                message={message}
                onAction={onAction}
              />
            ))}

            {busy ? (
              <div className={styles.typingRow} aria-live="polite" aria-label="Ask JetPakistan is thinking">
                <div className={styles.messageAvatar} aria-hidden="true">
                  <Plane className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
                </div>
                <div className={styles.typingBubble}>
                  <span />
                  <span />
                  <span />
                </div>
              </div>
            ) : null}

            {error ? (
              <div className={styles.error} role="alert">
                <span>{error}</span>
                <button type="button" onClick={() => setError(null)}>
                  Dismiss
                </button>
              </div>
            ) : null}
          </div>

          <footer className={styles.composer}>
            <form className={styles.inputBar} onSubmit={onSubmit}>
              <input
                ref={inputRef}
                type="text"
                value={input}
                onChange={(event) => setInput(event.target.value)}
                placeholder="Type your message…"
                aria-label="Message Ask JetPakistan"
                autoComplete="off"
                maxLength={1200}
                disabled={busy}
              />

              <button
                type="submit"
                className={styles.sendButton}
                aria-label="Send message"
                disabled={busy || input.trim().length === 0}
              >
                <Send className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
              </button>
            </form>

            <p className={styles.powered}>
              Powered by JetPakistan AI <span aria-hidden="true">•</span> For travel assistance only
            </p>
          </footer>
        </section>
      ) : null}
    </>
  );
}

function MessageItem({
  message,
  onAction,
}: {
  message: ChatMessage;
  onAction: (action: ChatAction) => void;
}) {
  const isUser = message.role === "user";
  const isStaff = message.role === "staff";

  return (
    <div
      className={`${styles.messageRow} ${
        isUser ? styles.userRow : styles.assistantRow
      }`}
    >
      {!isUser ? (
        <div className={styles.messageAvatar} aria-hidden="true">
          {isStaff ? (
            <Headphones className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
          ) : (
            <Plane className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
          )}
        </div>
      ) : null}

      <div className={styles.messageColumn}>
        <div
          className={`${styles.messageBubble} ${
            isUser ? styles.userBubble : styles.assistantBubble
          }`}
        >
          <p className={styles.messageBody}>{message.body}</p>
        </div>

        {message.recommendations?.length ? (
          <div className={styles.recommendationList}>
            {message.recommendations.map((recommendation) => (
              <RecommendationCard
                key={recommendation.id}
                recommendation={recommendation}
              />
            ))}
          </div>
        ) : null}

        {message.actions?.length ? (
          <div className={styles.messageActions}>
            {message.actions.map((action) =>
              action.href && !action.action ? (
                <Link key={`${message.id}-${action.label}`} href={action.href}>
                  {action.label}
                </Link>
              ) : (
                <button
                  key={`${message.id}-${action.label}`}
                  type="button"
                  onClick={() => onAction(action)}
                >
                  {action.label}
                </button>
              ),
            )}
          </div>
        ) : null}
      </div>
    </div>
  );
}

function RecommendationCard({
  recommendation,
}: {
  recommendation: Recommendation;
}) {
  const href =
    recommendation.view_and_book_url ||
    recommendation.package_url ||
    recommendation.results_url;

  const formattedPrice =
    typeof recommendation.price === "number"
      ? `${recommendation.currency || "PKR"} ${new Intl.NumberFormat("en-PK").format(
          recommendation.price,
        )}`
      : null;

  return (
    <article className={styles.recommendation}>
      <div className={styles.recommendationTop}>
        <div>
          <strong>{recommendation.title || "Recommended option"}</strong>
          {recommendation.subtitle ? <p>{recommendation.subtitle}</p> : null}
        </div>
        {formattedPrice ? <b>{formattedPrice}</b> : null}
      </div>

      {recommendation.labels?.length ? (
        <div className={styles.recommendationLabels}>
          {recommendation.labels.map((label) => (
            <span key={label}>{label}</span>
          ))}
        </div>
      ) : null}

      {href ? (
        <Link href={href} className={styles.recommendationCta}>
          View &amp; Book{" "}
          <ArrowRight className={UI_ICON_ACTION_CLASS} strokeWidth={UI_ICON_STROKE} aria-hidden="true" />
        </Link>
      ) : null}
    </article>
  );
}
