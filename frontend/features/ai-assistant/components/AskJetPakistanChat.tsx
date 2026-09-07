"use client";

import { ensureLaravelCsrfToken } from "@/features/public-content/utils/laravel-api";
import { laravelApiPath } from "@/services/flight-search";
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
    icon: <PlaneIcon />,
  },
  {
    id: "groups",
    title: "Find Groups",
    description: "Group bookings and special fares",
    message: "Find Groups for Dubai",
    icon: <GroupIcon />,
  },
  {
    id: "booking",
    title: "Booking Help",
    description: "Manage or understand a booking",
    message: "How does booking work",
    icon: <BookingIcon />,
  },
  {
    id: "payment",
    title: "Payment Help",
    description: "Payments, refunds and invoices",
    message: "Payment help",
    icon: <PaymentIcon />,
  },
  {
    id: "travelers",
    title: "Saved Travelers",
    description: "Manage your traveler profiles",
    message: "Saved Travelers help",
    icon: <TravelerIcon />,
  },
  {
    id: "support",
    title: "Talk to Support",
    description: "Open the JetPakistan support centre",
    href: "/support",
    icon: <HeadsetIcon />,
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
    clearHash();
  }, [clearHash]);

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
        setOpen(true);
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

    setMessages((previous) => [
      ...previous,
      {
        id: `a-${Date.now()}`,
        role: "assistant",
        body,
        recommendations: Array.isArray(json.recommendations)
          ? (json.recommendations as Recommendation[])
          : undefined,
        actions: Array.isArray(json.actions)
          ? (json.actions as ChatAction[])
          : undefined,
      },
    ]);
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
            onClick={() => setOpen(true)}
            className={styles.fab}
          >
            <AssistantFabIcon />
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
                <PlaneIcon />
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
                  <MoreIcon />
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
                <MinimizeIcon />
              </button>

              <button
                type="button"
                className={styles.headerIconButton}
                aria-label="Close Ask JetPakistan"
                onClick={close}
              >
                <CloseIcon />
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
                    <PlaneIcon />
                  </div>
                  <div className={`${styles.messageBubble} ${styles.assistantBubble}`}>
                    <strong>Hi! I&apos;m Ask JetPakistan 👋</strong>
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
                      <ArrowRightIcon />
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
                  <PlaneIcon />
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
                <SendIcon />
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
          {isStaff ? <HeadsetIcon /> : <PlaneIcon />}
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
          View &amp; Book <ArrowRightIcon />
        </Link>
      ) : null}
    </article>
  );
}

function IconBase({
  children,
  viewBox = "0 0 24 24",
}: {
  children: ReactNode;
  viewBox?: string;
}) {
  return (
    <svg
      viewBox={viewBox}
      fill="none"
      aria-hidden="true"
      focusable="false"
    >
      {children}
    </svg>
  );
}

function AssistantFabIcon() {
  return (
    <IconBase>
      <path
        d="M5 5.8h14a2 2 0 0 1 2 2v7.4a2 2 0 0 1-2 2h-7.2L7.2 20v-2.8H5a2 2 0 0 1-2-2V7.8a2 2 0 0 1 2-2Z"
        stroke="currentColor"
        strokeWidth="1.7"
        strokeLinejoin="round"
      />
      <path
        d="m8.1 13.4 7.5-4.5.9.6-2.6 2.1 2.2 1.4-.8.7-3-1-2.4 1.9-.7-.4 1.4-2.3-2.5 1.5Z"
        fill="currentColor"
      />
    </IconBase>
  );
}

function PlaneIcon() {
  return (
    <IconBase>
      <path
        d="m4.2 13.2 6.2-2.3 4.7-6.2c.7-.9 1.9-1.3 3-.8 1 .5 1.3 1.7.7 2.7l-3.9 6.8 3.4 3.3-1.1 1.1-4.4-2-2.3 3.5-1.2-.5.9-4.3-4.9.2-1.1-1.5Z"
        fill="currentColor"
      />
    </IconBase>
  );
}

function GroupIcon() {
  return (
    <IconBase>
      <circle cx="9" cy="8" r="3" stroke="currentColor" strokeWidth="1.8" />
      <circle cx="16.5" cy="9" r="2.3" stroke="currentColor" strokeWidth="1.6" />
      <path d="M3.8 18c.4-3 2.1-4.6 5.2-4.6s4.8 1.6 5.2 4.6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
      <path d="M14.3 14.1c2.8-.5 4.8.8 5.6 3.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
    </IconBase>
  );
}

function BookingIcon() {
  return (
    <IconBase>
      <rect x="4" y="5.5" width="16" height="14" rx="2.4" stroke="currentColor" strokeWidth="1.8" />
      <path d="M8 3.8v3.5M16 3.8v3.5M4 9h16" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
      <path d="M8 12.5h3M8 15.8h6" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" />
    </IconBase>
  );
}

function PaymentIcon() {
  return (
    <IconBase>
      <rect x="3.5" y="5.5" width="17" height="13" rx="2.5" stroke="currentColor" strokeWidth="1.8" />
      <path d="M3.8 9.4h16.4M7 14.3h4" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </IconBase>
  );
}

function TravelerIcon() {
  return (
    <IconBase>
      <circle cx="12" cy="8" r="3.2" stroke="currentColor" strokeWidth="1.8" />
      <path d="M5.5 19c.5-4 2.7-6 6.5-6s6 2 6.5 6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </IconBase>
  );
}

function HeadsetIcon() {
  return (
    <IconBase>
      <path d="M5 13v-1.4a7 7 0 0 1 14 0V13" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
      <path d="M5 13.5A2.5 2.5 0 0 0 7.5 16H8v-5h-.5A2.5 2.5 0 0 0 5 13.5Zm14 0a2.5 2.5 0 0 1-2.5 2.5H16v-5h.5a2.5 2.5 0 0 1 2.5 2.5Z" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
      <path d="M15.5 18.2c-.8 1-2 1.6-3.5 1.6" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
    </IconBase>
  );
}

function ArrowRightIcon() {
  return (
    <IconBase>
      <path d="M7 12h10M13.5 8.5 17 12l-3.5 3.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" />
    </IconBase>
  );
}

function SendIcon() {
  return (
    <IconBase>
      <path d="m4 5 16 7-16 7 2.4-6.1L14 12 6.4 11.1 4 5Z" fill="currentColor" />
    </IconBase>
  );
}

function MoreIcon() {
  return (
    <IconBase>
      <circle cx="6" cy="12" r="1.6" fill="currentColor" />
      <circle cx="12" cy="12" r="1.6" fill="currentColor" />
      <circle cx="18" cy="12" r="1.6" fill="currentColor" />
    </IconBase>
  );
}

function MinimizeIcon() {
  return (
    <IconBase>
      <path d="M6 12h12" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </IconBase>
  );
}

function CloseIcon() {
  return (
    <IconBase>
      <path d="m7 7 10 10M17 7 7 17" stroke="currentColor" strokeWidth="2" strokeLinecap="round" />
    </IconBase>
  );
}
