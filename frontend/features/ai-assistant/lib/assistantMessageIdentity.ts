export type IdentityChatMessage = {
  id: string;
  role: "user" | "assistant" | "staff" | "system" | string;
  body: string;
  recommendations?: unknown;
  actions?: unknown;
};

type IncomingAssistant = {
  id: string;
  body: string;
  recommendations?: unknown;
  actions?: unknown;
};

type PolledMessage = {
  id: number | string;
  role: string;
  body: string;
  meta?: {
    recommendations?: unknown;
    actions?: unknown;
  };
};

/** Append assistant by server message_id only (POST /chat, handoff, resume). */
export function appendAssistantById(
  previous: IdentityChatMessage[],
  incoming: IncomingAssistant,
): IdentityChatMessage[] {
  if (previous.some((message) => message.id === incoming.id)) {
    return previous;
  }

  return [
    ...previous,
    {
      id: incoming.id,
      role: "assistant",
      body: incoming.body,
      recommendations: incoming.recommendations,
      actions: incoming.actions,
    },
  ];
}

/** Merge polled assistants by server id only — identical bodies with distinct ids both render. */
export function mergePolledAssistantsById(
  previous: IdentityChatMessage[],
  incoming: PolledMessage[],
  hooks: { advanceLastPollId?: (id: number) => void } = {},
): IdentityChatMessage[] {
  const known = new Set(previous.map((message) => message.id));
  const next = [...previous];

  for (const message of incoming) {
    const numericId = typeof message.id === "number" ? message.id : Number(message.id);
    if (Number.isFinite(numericId) && typeof hooks.advanceLastPollId === "function") {
      hooks.advanceLastPollId(numericId);
    }

    const id = String(message.id);
    if (known.has(id) || message.role === "user") {
      continue;
    }

    known.add(id);
    next.push({
      id,
      role: (message.role as IdentityChatMessage["role"]) || "assistant",
      body: message.body,
      recommendations: message.meta?.recommendations,
      actions: message.meta?.actions,
    });
  }

  return next;
}
