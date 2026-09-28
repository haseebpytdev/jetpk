export type IdentityChatRole = "user" | "assistant" | "staff" | "system";

type IdentityBase = {
  id: string;
  role: IdentityChatRole;
  body: string;
};

type IncomingAssistant<T extends IdentityBase> = {
  id: string;
  body: string;
  recommendations?: T extends { recommendations?: infer R } ? R : never;
  actions?: T extends { actions?: infer A } ? A : never;
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
export function appendAssistantById<T extends IdentityBase>(
  previous: T[],
  incoming: IncomingAssistant<T>,
): T[] {
  if (previous.some((message) => message.id === incoming.id)) {
    return previous;
  }

  return [
    ...previous,
    {
      id: incoming.id,
      role: "assistant",
      body: incoming.body,
      ...(incoming.recommendations !== undefined
        ? { recommendations: incoming.recommendations }
        : {}),
      ...(incoming.actions !== undefined ? { actions: incoming.actions } : {}),
    } as T,
  ];
}

/** Merge polled assistants by server id only — identical bodies with distinct ids both render. */
export function mergePolledAssistantsById<T extends IdentityBase>(
  previous: T[],
  incoming: PolledMessage[],
  hooks: { advanceLastPollId?: (id: number) => void } = {},
): T[] {
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

    const role: IdentityChatRole =
      message.role === "user" ||
      message.role === "assistant" ||
      message.role === "staff" ||
      message.role === "system"
        ? message.role
        : "assistant";

    known.add(id);
    next.push({
      id,
      role,
      body: message.body,
      ...(message.meta?.recommendations !== undefined
        ? { recommendations: message.meta.recommendations }
        : {}),
      ...(message.meta?.actions !== undefined ? { actions: message.meta.actions } : {}),
    } as T);
  }

  return next;
}
